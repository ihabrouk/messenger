<?php

namespace App\Messenger\Events;

use App\Messenger\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MessageDelivered Event
 *
 * Fired when a message is successfully delivered to recipient
 */
class MessageDelivered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}
}
