<?php

namespace Ihabrouk\Messenger\Enums;

enum MessagePriority: int
{
    case LOW = 1;
    case NORMAL = 5;
    case HIGH = 8;
    case URGENT = 10;

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }

    public function queuePriority(): int
    {
        return match ($this) {
            self::LOW => 50,
            self::NORMAL => 60,
            self::HIGH => 80,
            self::URGENT => 100,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::NORMAL => 'blue',
            self::HIGH => 'yellow',
            self::URGENT => 'red',
        };
    }
}
