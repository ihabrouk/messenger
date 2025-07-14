<?php

namespace App\Messenger\Jobs;

use App\Messenger\Models\Message;
use App\Messenger\Services\MessengerService;
use App\Messenger\Enums\MessageStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessScheduledMessageJob
 *
 * Background job for processing scheduled messages
 * Executes messages at their scheduled time
 */
class ProcessScheduledMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $messageId
    ) {}

    public function handle(MessengerService $messengerService): void
    {
        $message = Message::find($this->messageId);

        if (!$message) {
            Log::warning('ProcessScheduledMessageJob: Message not found', ['message_id' => $this->messageId]);
            return;
        }

        // Check if message is still scheduled
        if ($message->status !== MessageStatus::SCHEDULED) {
            Log::info('ProcessScheduledMessageJob: Message no longer scheduled', [
                'message_id' => $this->messageId,
                'status' => $message->status->value,
            ]);
            return;
        }

        // Check if scheduled time has arrived
        if ($message->scheduled_at && $message->scheduled_at->isFuture()) {
            Log::warning('ProcessScheduledMessageJob: Message scheduled for future', [
                'message_id' => $this->messageId,
                'scheduled_at' => $message->scheduled_at->toISOString(),
            ]);
            return;
        }

        Log::info('ProcessScheduledMessageJob: Processing scheduled message', [
            'message_id' => $this->messageId,
            'to' => $message->to,
            'scheduled_at' => $message->scheduled_at?->toISOString(),
        ]);

        try {
            // Create send data from message
            $sendData = $message->toSendData();

            // Send the message
            $response = $messengerService->send($sendData);

            Log::info('ProcessScheduledMessageJob: Scheduled message sent successfully', [
                'message_id' => $this->messageId,
                'provider_message_id' => $response->providerMessageId,
                'status' => $response->status->value,
            ]);

        } catch (\Exception $e) {
            $message->update([
                'status' => MessageStatus::FAILED,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            Log::error('ProcessScheduledMessageJob: Scheduled message failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
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
            ]);

            Log::error('ProcessScheduledMessageJob: Final failure', [
                'message_id' => $this->messageId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        $message = Message::find($this->messageId);

        return [
            'messenger:scheduled',
            'provider:' . ($message?->provider ?? 'unknown'),
            'channel:' . ($message?->channel ?? 'unknown'),
        ];
    }
}
