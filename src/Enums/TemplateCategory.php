<?php

namespace Ihabrouk\Messenger\Enums;

enum TemplateCategory: string
{
    case OTP = 'otp';
    case WELCOME = 'welcome';
    case VERIFICATION = 'verification';
    case MARKETING = 'marketing';
    case TRANSACTIONAL = 'transactional';
    case EMERGENCY = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::OTP => 'OTP',
            self::WELCOME => 'Welcome',
            self::VERIFICATION => 'Verification',
            self::MARKETING => 'Marketing',
            self::TRANSACTIONAL => 'Transactional',
            self::EMERGENCY => 'Emergency',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OTP => 'One-time passwords and authentication codes',
            self::WELCOME => 'Welcome messages for new members',
            self::VERIFICATION => 'Account verification messages',
            self::MARKETING => 'Promotional and marketing content',
            self::TRANSACTIONAL => 'Order confirmations, receipts, and updates',
            self::EMERGENCY => 'Urgent notifications and alerts',
        };
    }

    public function priority(): MessagePriority
    {
        return match ($this) {
            self::OTP => MessagePriority::URGENT,
            self::WELCOME => MessagePriority::NORMAL,
            self::VERIFICATION => MessagePriority::HIGH,
            self::MARKETING => MessagePriority::LOW,
            self::TRANSACTIONAL => MessagePriority::HIGH,
            self::EMERGENCY => MessagePriority::URGENT,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OTP => 'heroicon-o-shield-check',
            self::WELCOME => 'heroicon-o-hand-raised',
            self::VERIFICATION => 'heroicon-o-check-badge',
            self::MARKETING => 'heroicon-o-megaphone',
            self::TRANSACTIONAL => 'heroicon-o-receipt-percent',
            self::EMERGENCY => 'heroicon-o-exclamation-triangle',
        };
    }
}
