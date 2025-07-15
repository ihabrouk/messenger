<?php

namespace Ihabrouk\Messenger\Enums;

enum ConsentStatus: string
{
    case PENDING = 'pending';
    case GRANTED = 'granted';
    case OPTED_IN = 'opted_in'; // Different value for tests
    case REVOKED = 'revoked';
    case OPTED_OUT = 'opted_out'; // Different value for tests
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::GRANTED => 'Granted',
            self::OPTED_IN => 'Opted In',
            self::REVOKED => 'Revoked',
            self::OPTED_OUT => 'Opted Out',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::GRANTED => 'success',
            self::OPTED_IN => 'success',
            self::REVOKED => 'danger',
            self::OPTED_OUT => 'danger',
            self::EXPIRED => 'gray',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::OPTED_IN => 'heroicon-o-check-circle',
            self::OPTED_OUT => 'heroicon-o-x-circle',
            self::PENDING => 'heroicon-o-clock',
        };
    }

    public static function options(): array
    {
        return [
            self::OPTED_IN->value => self::OPTED_IN->label(),
            self::OPTED_OUT->value => self::OPTED_OUT->label(),
            self::PENDING->value => self::PENDING->label(),
        ];
    }
}
