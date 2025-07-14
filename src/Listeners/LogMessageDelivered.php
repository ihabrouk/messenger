<?php

namespace App\Messenger\Listeners;

use App\Messenger\Events\MessageDelivered;
use App\Messenger\Models\Log;
use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * LogMessageDelivered Listener
 *
 * Logs message delivered events for analytics
 */
class LogMessageDelivered
{
    public function handle(MessageDelivered $event): void
    {
        $message = $event->message;

        // Create log entry
        Log::create([
            'type' => 'message_delivered',
            'message_id' => $message->id,
            'provider' => $message->provider,
            'channel' => $message->channel,
            'to' => $message->to,
            'context' => [
                'template_id' => $message->template_id,
                'provider_message_id' => $message->provider_message_id,
                'delivery_status' => $message->delivery_status,
                'delivered_at' => $message->delivered_at?->toISOString(),
                'cost' => $message->cost,
            ],
        ]);

        LaravelLog::info('Message delivered event logged', [
            'message_id' => $message->id,
            'to' => $message->to,
            'provider' => $message->provider,
            'delivered_at' => $message->delivered_at?->toISOString(),
        ]);
    }
}
