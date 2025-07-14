<?php

namespace App\Messenger\Events;

use App\Messenger\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MessageBounced Event
 *
 * Fired when a message bounces (invalid recipient, etc.)
 */
class MessageBounced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}
}
