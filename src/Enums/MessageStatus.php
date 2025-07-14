<?php

namespace App\Messenger\Enums;

enum MessageStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::QUEUED => 'blue',
            self::SENDING => 'yellow',
            self::SENT => 'green',
            self::DELIVERED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'gray',
            self::EXPIRED => 'warning',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
            self::EXPIRED,
        ]);
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::SENT,
            self::DELIVERED,
        ]);
    }
}
