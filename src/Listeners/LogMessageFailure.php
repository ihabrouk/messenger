<?php

namespace App\Messenger\Listeners;

use App\Messenger\Events\MessageFailed;
use App\Messenger\Models\Log;
use App\Messenger\Jobs\RetryFailedMessageJob;
use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * LogMessageFailure Listener
 *
 * Logs message failures and optionally schedules retries
 */
class LogMessageFailure
{
    public function handle(MessageFailed $event): void
    {
        $message = $event->message;

        // Create log entry
        Log::create([
            'type' => 'message_failed',
            'message_id' => $message->id,
            'provider' => $message->provider,
            'channel' => $message->channel,
            'to' => $message->to,
            'context' => [
                'template_id' => $message->template_id,
                'provider_message_id' => $message->provider_message_id,
                'error_message' => $message->error_message,
                'error_code' => $message->error_code,
                'retry_count' => $message->retry_count,
                'failed_at' => $message->failed_at?->toISOString(),
            ],
        ]);

        LaravelLog::warning('Message failed event logged', [
            'message_id' => $message->id,
            'to' => $message->to,
            'provider' => $message->provider,
            'error' => $message->error_message,
            'retry_count' => $message->retry_count,
        ]);

        // Schedule retry if eligible
        $this->scheduleRetryIfEligible($message);
    }

    protected function scheduleRetryIfEligible($message): void
    {
        $maxRetries = config('messenger.max_retries', 3);
        $retryCount = $message->retry_count ?? 0;

        // Check if we should retry
        if ($retryCount < $maxRetries && $this->isRetryableError($message->error_message)) {
            $delay = $this->calculateRetryDelay($retryCount);

            RetryFailedMessageJob::dispatch($message->id)
                ->delay($delay)
                ->onQueue('retries');

            LaravelLog::info('Retry scheduled for failed message', [
                'message_id' => $message->id,
                'retry_count' => $retryCount + 1,
                'delay_minutes' => $delay,
            ]);
        }
    }

    protected function isRetryableError(?string $errorMessage): bool
    {
        if (!$errorMessage) {
            return true; // Default to retryable for unknown errors
        }

        $nonRetryablePatterns = [
            'invalid phone number',
            'blacklisted',
            'unsubscribed',
            'authentication failed',
            'insufficient credits',
        ];

        foreach ($nonRetryablePatterns as $pattern) {
            if (stripos($errorMessage, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    protected function calculateRetryDelay(int $retryCount): int
    {
        // Exponential backoff: 5, 15, 45 minutes
        return min(5 * pow(3, $retryCount), 60);
    }
}
