<?php

namespace Ihabrouk\Messenger\Jobs;

use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Services\MessengerService;
use Ihabrouk\Messenger\Enums\MessageStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RetryFailedMessageJob
 *
 * Background job for retrying failed messages with exponential backoff
 */
class RetryFailedMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Don't retry the retry job itself
    public int $timeout = 60;

    public function __construct(
        public string $messageId
    ) {}

    public function handle(MessengerService $messengerService): void
    {
        $message = Message::find($this->messageId);

        if (!$message) {
            Log::warning('RetryFailedMessageJob: Message not found', ['message_id' => $this->messageId]);
            return;
        }

        // Check if message is still in failed state
        if ($message->status !== MessageStatus::FAILED) {
            Log::info('RetryFailedMessageJob: Message no longer failed', [
                'message_id' => $this->messageId,
                'status' => $message->status->value,
            ]);
            return;
        }

        // Check retry limit
        $maxRetries = config('messenger.max_retries', 3);
        $currentRetries = $message->retry_count ?? 0;

        if ($currentRetries >= $maxRetries) {
            Log::warning('RetryFailedMessageJob: Maximum retries exceeded', [
                'message_id' => $this->messageId,
                'retry_count' => $currentRetries,
                'max_retries' => $maxRetries,
            ]);
            return;
        }

        Log::info('RetryFailedMessageJob: Retrying failed message', [
            'message_id' => $this->messageId,
            'retry_count' => $currentRetries + 1,
            'original_error' => $message->error_message,
        ]);

        try {
            // Use the messenger service retry method
            $response = $messengerService->retry($this->messageId);

            Log::info('RetryFailedMessageJob: Message retry successful', [
                'message_id' => $this->messageId,
                'provider_message_id' => $response->providerMessageId,
                'status' => $response->status->value,
            ]);

        } catch (\Exception $e) {
            Log::error('RetryFailedMessageJob: Retry failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'retry_count' => $currentRetries + 1,
            ]);

            // Update retry count even if failed
            $message->increment('retry_count');

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $message = Message::find($this->messageId);

        if ($message) {
            Log::error('RetryFailedMessageJob: Final retry failure', [
                'message_id' => $this->messageId,
                'error' => $exception->getMessage(),
                'retry_count' => $message->retry_count,
            ]);
        }
    }

    public function tags(): array
    {
        $message = Message::find($this->messageId);

        return [
            'messenger:retry',
            'provider:' . ($message?->provider ?? 'unknown'),
            'channel:' . ($message?->channel ?? 'unknown'),
        ];
    }
}
