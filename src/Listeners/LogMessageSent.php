<?php

namespace App\Messenger\Listeners;

use App\Messenger\Events\MessageSent;
use App\Messenger\Models\Log;
use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * LogMessageSent Listener
 *
 * Logs message sent events for analytics and debugging
 */
class LogMessageSent
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        // Create log entry
        Log::create([
            'type' => 'message_sent',
            'message_id' => $message->id,
            'provider' => $message->provider,
            'channel' => $message->channel,
            'to' => $message->to,
            'context' => [
                'template_id' => $message->template_id,
                'provider_message_id' => $message->provider_message_id,
                'cost' => $message->cost,
                'priority' => $message->priority?->value,
            ],
        ]);

        LaravelLog::info('Message sent event logged', [
            'message_id' => $message->id,
            'to' => $message->to,
            'provider' => $message->provider,
        ]);
    }
}
