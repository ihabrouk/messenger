# Laravel Messenger Package

A comprehensive Laravel package for multi-provider messaging (SMS, WhatsApp) with FilamentPHP integration.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ihabrouk/messenger.svg?style=flat-square)](https://packagist.org/packages/ihabrouk/messenger)
[![Total Downloads](https://img.shields.io/packagist/dt/ihabrouk/messenger.svg?style=flat-square)](https://packagist.org/packages/ihabrouk/messenger)

## Features

- 🚀 **Multi-Provider Support**: SMS Misr, Twilio (SMS & WhatsApp), and extensible architecture
- 📱 **Multiple Channels**: SMS, WhatsApp, OTP with automatic fallback
- 🎨 **FilamentPHP Integration**: Admin panels, forms, actions, and components
- 📋 **Template System**: Dynamic templates with variable substitution
- 📊 **Bulk Messaging**: Send to thousands of recipients with progress tracking
- ⚡ **Queue Integration**: Background processing with priority queues
- 📈 **Analytics**: Delivery tracking, cost monitoring, and reporting
- 🔒 **Security**: GDPR compliance, consent management, and rate limiting
- 🛡️ **Circuit Breaker**: Automatic failover for provider reliability
- 🎯 **Automation**: Triggered messaging based on events

## Version Support

| Version | Laravel | Filament | PHP | Status |
|---------|---------|----------|-----|--------|
| **2.x** | 11.0+ | 4.0+ | 8.2+ | ✅ Active Development |
| **1.x** | 10.0-11.x | 3.0+ | 8.1+ | 🔧 Maintenance |

### Choosing Your Version

- **Use v2.x** if you're on Laravel 11+ and can upgrade to Filament v4
- **Use v1.x** if you need to stay on Laravel 10 or Filament v3

```bash
# For new projects (recommended)
composer require "ihabrouk/messenger:^2.0"

# For projects using Filament v3
composer require "ihabrouk/messenger:^1.0"
```

## Installation

You can install the package via composer:

```bash
composer require ihabrouk/messenger
```

### Quick Setup
```bash
# Publish and run migrations (REQUIRED)
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-migrations"
php artisan migrate

# Publish configuration
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-config"
```

### Installation Issues?
If you encounter "Class not found" errors:

```bash
# Run diagnostic command
php artisan messenger:diagnose

# See emergency fix guide
# Check EMERGENCY_FIX.md for detailed troubleshooting
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="messenger-config"
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="messenger-migrations"
php artisan migrate
```

Optionally, publish the views and language files:

```bash
php artisan vendor:publish --tag="messenger-views"
php artisan vendor:publish --tag="messenger-lang"
```

## Environment Variables

Add these variables to your `.env` file:

```bash
# Default Provider
MESSENGER_DEFAULT_PROVIDER=smsmisr

# SMS Misr Configuration
SMS_MISR_API_USERNAME=your_username
SMS_MISR_API_PASSWORD=your_password
SMS_MISR_SENDER_ID=your_sender_id
SMS_MISR_ENVIRONMENT=2  # 1 for Live, 2 for Test

# Twilio Configuration
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=your_phone_number

# Queue Configuration
MESSENGER_QUEUE_CONNECTION=redis
MESSENGER_QUEUE_NAME=messenger

# Analytics
MESSENGER_ANALYTICS_ENABLED=true
```

## Quick Start

### Basic Usage

```php
use Ihabrouk\Messenger\Facades\Messenger;

// Send a simple SMS
Messenger::send([
    'recipient_phone' => '+1234567890',
    'content' => 'Hello, this is a test message!',
    'provider' => 'smsmisr', // optional
    'channel' => 'sms'       // optional
]);

// Send using templates
Messenger::sendFromTemplate('welcome_user', [
    'recipient_phone' => '+1234567890',
    'variables' => [
        'user_name' => 'John Doe',
        'company' => 'Acme Corp'
    ]
]);
```

### Bulk Messaging

```php
use Ihabrouk\Messenger\Models\Batch;

$batch = Batch::create([
    'name' => 'Newsletter Campaign',
    'template_id' => $template->id,
    'provider' => 'smsmisr',
    'channel' => 'sms'
]);

$recipients = [
    ['phone' => '+1234567890', 'variables' => ['name' => 'John']],
    ['phone' => '+0987654321', 'variables' => ['name' => 'Jane']],
];

Messenger::bulkSend($batch, $recipients);
```

### FilamentPHP Integration

Add to your Filament resources:

```php
use Ihabrouk\Messenger\Actions\SendMessageAction;

// In your table actions
SendMessageAction::make()
    ->phoneColumn('phone_number')
    ->nameColumn('full_name')
```

## Provider Configuration

### SMS Misr Setup

1. Register at [SMS Misr](https://smsmisr.com)
2. Get your API credentials
3. Configure webhooks for delivery tracking

```php
// config/messenger.php
'providers' => [
    'smsmisr' => [
        'driver' => 'smsmisr',
        'username' => env('SMS_MISR_API_USERNAME'),
        'password' => env('SMS_MISR_API_PASSWORD'),
        'sender_id' => env('SMS_MISR_SENDER_ID'),
        // ... other options
    ]
]
```

### Twilio Setup

1. Create a Twilio account
2. Get your Account SID and Auth Token
3. Configure your phone number or WhatsApp sender

```php
// config/messenger.php
'providers' => [
    'twilio' => [
        'driver' => 'twilio',
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
        // ... other options
    ]
]
```

## Advanced Features

### Custom Providers

Create your own messaging provider:

```bash
php artisan messenger:make-driver CustomProvider
```

### Templates

Manage templates through Filament admin or programmatically:

```php
use Ihabrouk\Messenger\Models\Template;

Template::create([
    'name' => 'welcome_sms',
    'content' => [
        'en' => 'Welcome {{name}}! Your account is ready.',
        'ar' => 'مرحباً {{name}}! حسابك جاهز الآن.'
    ],
    'channels' => ['sms'],
    'category' => 'welcome'
]);
```

### Analytics & Monitoring

Access delivery analytics:

```php
use Ihabrouk\Messenger\Services\AnalyticsService;

$analytics = app(AnalyticsService::class);
$stats = $analytics->getDeliveryStats('last_30_days');
```

### Event Listeners

Listen to messaging events:

```php
use Ihabrouk\Messenger\Events\MessageSent;

Event::listen(MessageSent::class, function ($event) {
    // Handle successful message sending
    Log::info('Message sent', ['message_id' => $event->message->id]);
});
```

## Troubleshooting

### Diagnostic Command
```bash
# Run this to diagnose installation issues
php artisan messenger:diagnose
```

### Common Issues
- **"Class not found" errors**: See [EMERGENCY_FIX.md](EMERGENCY_FIX.md)
- **Migration issues**: See [INSTALLATION_TROUBLESHOOTING.md](INSTALLATION_TROUBLESHOOTING.md)
- **Provider setup**: See [INSTALLATION.md](INSTALLATION.md)

### Available Commands
```bash
php artisan messenger:diagnose              # Diagnose installation issues
php artisan messenger:list-providers        # List available providers
php artisan messenger:test-provider         # Test provider configuration
php artisan messenger:send                  # Send a test message
```

## Testing

```bash
composer test
```

## Security

If you discover any security-related issues, please email security@example.com instead of using the issue tracker.

## Credits

- [Ihab Brouk](https://github.com/ihabrouk)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Support

For support, email support@example.com or join our Discord channel.
