<?php

namespace App\Messenger\Enums;

enum MessageType: string
{
    case SMS = 'sms';
    case OTP = 'otp';
    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';

    public function label(): string
    {
        return match ($this) {
            self::SMS => 'SMS',
            self::OTP => 'OTP',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'Email',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SMS => 'heroicon-o-chat-bubble-left',
            self::OTP => 'heroicon-o-shield-check',
            self::WHATSAPP => 'heroicon-o-device-phone-mobile',
            self::EMAIL => 'heroicon-o-envelope',
        };
    }

    public function supportedProviders(): array
    {
        return match ($this) {
            self::SMS => ['smsmisr', 'twilio'],
            self::OTP => ['smsmisr'],
            self::WHATSAPP => ['twilio'],
            self::EMAIL => [],
        };
    }
}
