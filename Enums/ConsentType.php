<?php

namespace Ihabrouk\Messenger\Enums;

enum ConsentType: string
{
    case MARKETING = 'marketing';
    case NOTIFICATIONS = 'notifications';
    case REMINDERS = 'reminders';
    case ALERTS = 'alerts';
    case TRANSACTIONAL = 'transactional';
    case ALL = 'all';

    public function label(): string
    {
        return match($this) {
            self::MARKETING => 'Marketing',
            self::NOTIFICATIONS => 'Notifications',
            self::REMINDERS => 'Reminders',
            self::ALERTS => 'Alerts',
            self::TRANSACTIONAL => 'Transactional',
            self::ALL => 'All Messages',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::MARKETING => 'Promotional messages, newsletters, offers',
            self::NOTIFICATIONS => 'Service notifications and updates',
            self::REMINDERS => 'Appointment and event reminders',
            self::ALERTS => 'Important alerts and warnings',
            self::TRANSACTIONAL => 'Order confirmations, receipts, account updates',
            self::ALL => 'All types of messages',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::MARKETING => 'primary',
            self::NOTIFICATIONS => 'info',
            self::REMINDERS => 'warning',
            self::ALERTS => 'danger',
            self::TRANSACTIONAL => 'success',
            self::ALL => 'gray',
        };
    }

    public static function options(): array
    {
        return [
            self::MARKETING->value => self::MARKETING->label(),
            self::TRANSACTIONAL->value => self::TRANSACTIONAL->label(),
            self::ALL->value => self::ALL->label(),
        ];
    }
}
