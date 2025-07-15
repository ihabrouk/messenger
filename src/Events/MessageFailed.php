<?php

namespace Ihabrouk\Messenger\Events;

use Ihabrouk\Messenger\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MessageFailed Event
 *
 * Fired when a message fails to send or deliver
 */
class MessageFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}
}
