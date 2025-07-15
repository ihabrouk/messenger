<?php

namespace Ihabrouk\Messenger\Enums;

enum MessageProvider: string
{
    case SMSMISR = 'smsmisr';
    case TWILIO = 'twilio';

    public function label(): string
    {
        return match ($this) {
            self::SMSMISR => 'SMS Misr',
            self::TWILIO => 'Twilio',
        };
    }

    public function driverClass(): string
    {
        return match ($this) {
            self::SMSMISR => 'Ihabrouk\Messenger\Drivers\SmsMisrDriver',
            self::TWILIO => 'Ihabrouk\Messenger\Drivers\TwilioDriver',
        };
    }

    public function supportedChannels(): array
    {
        return match ($this) {
            self::SMSMISR => [MessageType::SMS, MessageType::OTP],
            self::TWILIO => [MessageType::SMS, MessageType::WHATSAPP],
        };
    }

    public function maxRecipients(): int
    {
        return match ($this) {
            self::SMSMISR => 5000,
            self::TWILIO => 1000,
        };
    }
}
