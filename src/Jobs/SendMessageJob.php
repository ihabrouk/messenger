<?php

namespace Ihabrouk\Messenger\Jobs;

use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Services\MessengerService;
use Ihabrouk\Messenger\Services\MessageProviderFactory;
use Ihabrouk\Messenger\Enums\MessageStatus;
use Ihabrouk\Messenger\Exceptions\MessengerException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * SendMessageJob
 *
 * Background job for sending individual messages
 * Includes retry logic and exponential backoff
 */
class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 60;
    public int $backoff = 30;

    public function __construct(
        public string $messageId
    ) {}

    public function handle(MessageProviderFactory $providerFactory): void
    {
        $message = Message::find($this->messageId);

        if (!$message) {
            Log::warning('SendMessageJob: Message not found', ['message_id' => $this->messageId]);
            return;
        }

        // Skip if message is no longer in a sendable state
        if (!in_array($message->status, [MessageStatus::PENDING, MessageStatus::QUEUED, MessageStatus::RETRYING])) {
            Log::info('SendMessageJob: Message not in sendable state', [
                'message_id' => $this->messageId,
                'status' => $message->status->value,
            ]);
            return;
        }

        Log::info('SendMessageJob: Processing message', [
            'message_id' => $this->messageId,
            'to' => $message->to,
            'provider' => $message->provider,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Update status to sending
            $message->update(['status' => MessageStatus::SENDING]);

            // Get provider instance
            $provider = $providerFactory->make($message->provider, $message->channel);

            // Create send data from message
            $sendData = $message->toSendData();

            // Send message
            $response = $provider->send($sendData);

            // Update message with response
            $message->update([
                'status' => $response->status,
                'provider_message_id' => $response->providerMessageId,
                'cost' => $response->cost,
                'sent_at' => $response->sentAt ?? now(),
                'error_message' => $response->errorMessage,
            ]);

            Log::info('SendMessageJob: Message sent successfully', [
                'message_id' => $this->messageId,
                'provider_message_id' => $response->providerMessageId,
                'status' => $response->status->value,
                'cost' => $response->cost,
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($message, $e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    public function failed(\Throwable $exception): void
    {
        $message = Message::find($this->messageId);

        if ($message) {
            $message->update([
                'status' => MessageStatus::FAILED,
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
                'retry_count' => $this->attempts(),
            ]);

            Log::error('SendMessageJob: Final failure after all retries', [
                'message_id' => $this->messageId,
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
            ]);
        }
    }

    protected function handleFailure(Message $message, \Exception $exception): void
    {
        $retryCount = $this->attempts();

        $message->update([
            'status' => MessageStatus::RETRYING,
            'error_message' => $exception->getMessage(),
            'retry_count' => $retryCount,
        ]);

        Log::warning('SendMessageJob: Message send failed, will retry', [
            'message_id' => $this->messageId,
            'error' => $exception->getMessage(),
            'attempt' => $retryCount,
            'max_tries' => $this->tries,
        ]);
    }

    public function middleware(): array
    {
        return [
            // Prevent overlapping sends of the same message
            new WithoutOverlapping($this->messageId),

            // Rate limit per provider
            (new RateLimited('messenger-send'))
                ->dontRelease(),
        ];
    }

    /**
     * Calculate exponential backoff delay
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30s, 1m, 2m delays
    }

    /**
     * Determine if the job should be retried based on exception
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Get tags for monitoring
     */
    public function tags(): array
    {
        $message = Message::find($this->messageId);

        return [
            'messenger:send',
            'provider:' . ($message?->provider ?? 'unknown'),
            'channel:' . ($message?->channel ?? 'unknown'),
            'priority:' . ($message?->priority?->value ?? 'normal'),
        ];
    }
}
