# Laravel Messenger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/your-github-username/laravel-messenger.svg?style=flat-square)](https://packagist.org/packages/your-github-username/laravel-messenger)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/your-github-username/laravel-messenger/run-tests?label=tests)](https://github.com/your-github-username/laravel-messenger/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/your-github-username/laravel-messenger/Check%20&%20fix%20styling?label=code%20style)](https://github.com/your-github-username/laravel-messenger/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/your-github-username/laravel-messenger.svg?style=flat-square)](https://packagist.org/packages/your-github-username/laravel-messenger)

A comprehensive, enterprise-grade messaging package for Laravel with multi-provider support, advanced analytics, GDPR compliance, and real-time monitoring.

## 🚀 Features

### Core Messaging
- **Multi-Provider Support**: Twilio, AWS SNS, and more
- **Multiple Channels**: SMS, Email, Push notifications
- **Template Management**: Dynamic templates with variables
- **Batch Processing**: Send thousands of messages efficiently
- **Queue Integration**: Background processing with priority queues

### Advanced Analytics
- **Real-Time Dashboard**: Live metrics with auto-refresh
- **Comprehensive Reporting**: Delivery rates, costs, engagement
- **Provider Analytics**: Performance comparison across providers
- **Cost Tracking**: Detailed cost analysis and optimization
- **Trend Analysis**: Historical data and forecasting

### GDPR Compliance
- **Consent Management**: Double opt-in verification
- **Data Protection**: Automatic anonymization and deletion
- **Right to be Forgotten**: Complete data removal
- **Audit Logging**: Full compliance tracking
- **Preference Management**: Granular consent controls

### Enterprise Features
- **Circuit Breaker Pattern**: Automatic failover protection
- **Redis Caching**: Optimized performance
- **Error Tracking**: Sentry integration
- **Monitoring**: System health and alerts
- **Automation**: Smart retry and escalation

## 📋 Requirements

- PHP 8.2+
- Laravel 11+
- Redis (for caching and queues)
- MySQL/PostgreSQL/SQLite

## 🛠 Installation

You can install the package via Composer:

```bash
composer require your-github-username/laravel-messenger
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=messenger-config
php artisan vendor:publish --tag=messenger-migrations
php artisan vendor:publish --tag=messenger-views
```

Run the migrations:

```bash
php artisan migrate
```

## ⚙️ Configuration

Configure your providers in `config/messenger.php`:

```php
'providers' => [
    'twilio' => [
        'driver' => 'twilio',
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
    
    'aws_sns' => [
        'driver' => 'aws_sns',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'from' => env('AWS_SNS_FROM'),
    ],
],
```

Add to your `.env` file:

```env
# Twilio Configuration
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=+1234567890

# AWS SNS Configuration
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_SNS_FROM=+1234567890

# Consent Management
MESSENGER_CONSENT_ENABLED=true
MESSENGER_DOUBLE_OPT_IN=true

# Analytics
MESSENGER_ANALYTICS_ENABLED=true
MESSENGER_REAL_TIME_ANALYTICS=true

# Caching
MESSENGER_CACHING_ENABLED=true
MESSENGER_CACHE_DRIVER=redis
```

## 🎯 Usage

### Basic Message Sending

```php
use YourNamespace\LaravelMessenger\Services\MessengerService;

$messenger = app(MessengerService::class);

// Send a simple SMS
$message = $messenger->send([
    'to' => '+1234567890',
    'message' => 'Hello, World!',
    'channel' => 'sms',
    'provider' => 'twilio'
]);

// Send using a template
$message = $messenger->sendTemplate([
    'to' => '+1234567890',
    'template' => 'welcome-message',
    'variables' => ['name' => 'John Doe'],
    'channel' => 'sms'
]);
```

### Consent Management

```php
use YourNamespace\LaravelMessenger\Services\ConsentService;

$consentService = app(ConsentService::class);

// Process opt-in
$consent = $consentService->processOptIn('+1234567890', 'marketing');

// Check consent before sending
if ($consentService->hasConsent('+1234567890', 'marketing')) {
    // Send marketing message
}

// Process opt-out
$consentService->processOptOut('+1234567890');
```

### Analytics Dashboard

Add to your Filament admin panel or Blade view:

```php
// In a Filament page
<livewire:messenger-analytics-dashboard />
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

## 📈 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## 📄 Credits

- [Your Name](https://github.com/your-github-username)
- [All Contributors](../../contributors)

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
