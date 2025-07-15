# Laravel / Filamentphp Messenger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ihabrouk/messenger.svg?style=flat-square)](https://packagist.org/packages/ihabrouk/messenger)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/ihabrouk/messenger/run-tests?label=tests)](https://github.com/ihabrouk/messenger/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/ihabrouk/messenger/Check%20&%20fix%20styling?label=code%20style)](https://github.com/ihabrouk/messenger/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ihabrouk/messenger.svg?style=flat-square)](https://packagist.org/packages/ihabrouk/messenger)

A comprehensive, enterprise-grade messaging package for multi-provider support, leveraging Laravel FilamentPHP to provide advanced analytics, GDPR compliance, and real-time monitoring.

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
composer require ihabrouk/messenger
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
use ihabrouk\Messenger\Services\MessengerService;

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

### 🎨 Filament Integration

This package provides ready-to-use Filament actions for seamless integration with your admin panels.

#### Individual Message Actions

Add individual message sending to any Filament resource:

```php
use ihabrouk\Messenger\Actions\SendMessageAction;

// In your table actions
->actions([
    Tables\Actions\EditAction::make(),
    
    // Add the Send Message Action
    SendMessageAction::make()
        ->phoneField('phone')      // Map to your phone field
        ->nameField('full_name')   // Map to your name field (optional)
        ->visible(fn ($record) => !empty($record->phone))
        ->modalHeading(fn ($record) => "Send Message to {$record->name}")
        ->successNotificationTitle('Message sent successfully!')
        ->before(function ($action, $record) {
            // Pre-fill the form with recipient data
            $action->fillForm([
                'recipient_phone' => $record->phone,
                'recipient_name' => $record->name,
            ]);
        }),
])
```

#### Bulk Message Actions

Send messages to multiple records at once:

```php
use ihabrouk\Messenger\Actions\BulkMessageAction;

// In your table bulk actions
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\DeleteBulkAction::make(),
        
        // Add the Bulk Message Action
        BulkMessageAction::make()
            ->phoneField('phone')       // Map to your phone field
            ->nameField('full_name')    // Map to your name field (optional)
            ->maxRecipients(1000)       // Set recipient limit
            ->requiresConfirmation(true)
            ->modalHeading('Send Bulk Message Campaign')
            ->successNotificationTitle('Bulk campaign started!')
            ->before(function ($action, $records) {
                // Validate recipients have phone numbers
                $validRecipients = $records->filter(fn ($record) => !empty($record->phone));
                
                if ($validRecipients->isEmpty()) {
                    $action->halt();
                    \Filament\Notifications\Notification::make()
                        ->title('No valid recipients')
                        ->body('Selected users must have phone numbers.')
                        ->danger()
                        ->send();
                    return;
                }
                
                // Pre-fill recipient count
                $action->fillForm([
                    'recipient_count' => $validRecipients->count() . ' valid recipients',
                ]);
            }),
    ]),
])
```

#### Database Requirements

Add a phone field to your model for messaging functionality:

```php
// In a migration - add to any existing table
Schema::table('your_table_name', function (Blueprint $table) {
    $table->string('phone', 20)->nullable();
});

// Or when creating a new table
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('phone', 20)->nullable();
    $table->timestamps();
});
```

### 🎛️ Action Configuration

#### Customizing SendMessageAction

```php
SendMessageAction::make()
    ->phoneField('phone')                   // Custom phone field
    ->nameField('full_name')                // Custom name field (optional)
    ->label('Send SMS')                     // Custom label
    ->icon('heroicon-o-chat-bubble-left')   // Custom icon
    ->color('primary')                      // Custom color
    ->modalWidth('2xl')                     // Modal size
    ->requiresConfirmation(false)           // Disable confirmation
    ->successNotificationTitle('SMS Sent!') // Custom success message
    ->before(function ($action, $record) {
        // Example: Custom validation before sending
        // Check if your model has a consent field
        if (isset($record->consent_marketing) && !$record->consent_marketing) {
            $action->halt();
            Notification::make()
                ->title('No Marketing Consent')
                ->body('User has not consented to marketing messages.')
                ->warning()
                ->send();
        }
    })
    ->after(function ($action, $record, $data) {
        // Custom logic after sending - example assumes your model has this field
        if (method_exists($record, 'update') && \Illuminate\Support\Facades\Schema::hasColumn($record->getTable(), 'last_message_sent')) {
            $record->update(['last_message_sent' => now()]);
        }
    })
```

#### Customizing BulkMessageAction

```php
BulkMessageAction::make()
    ->phoneField('phone')                   // Custom phone field
    ->nameField('full_name')                // Custom name field (optional)
    ->maxRecipients(100)                    // Limit recipients
    ->label('Send Campaign')                // Custom label
    ->icon('heroicon-o-megaphone')          // Custom icon
    ->color('success')                      // Custom color
    ->modalWidth('4xl')                     // Larger modal
    ->requiresConfirmation(true)            // Require confirmation
    ->before(function ($action, $records) {
        // Validate all records have required fields
        $invalidRecords = $records->filter(fn ($record) => 
            empty($record->phone) || !$record->marketing_consent
        );
        
        if ($invalidRecords->isNotEmpty()) {
            $action->halt();
            Notification::make()
                ->title('Invalid Recipients')
                ->body($invalidRecords->count() . ' recipients cannot receive marketing messages.')
                ->warning()
                ->send();
        }
    })
```

### Consent Management

```php
use ihabrouk\Messenger\Services\ConsentService;

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

### 📱 Template Management

#### Creating Templates

```php
use ihabrouk\Messenger\Models\Template;

$template = Template::create([
    'name' => 'welcome-sms',
    'display_name' => 'Welcome SMS',
    'subject' => null, // For SMS, subject is null
    'body' => 'Welcome {{ name }}! Your account is ready. Login at {{ login_url }}',
    'variables' => ['name', 'login_url'],
    'channels' => ['sms'],
    'message_type' => 'transactional',
    'is_active' => true,
    'approval_status' => 'approved'
]);
```

#### Using Templates in Actions

Templates are automatically loaded in Filament actions and can be selected from a dropdown. Variables are dynamically detected and form fields are generated automatically.

### 🔄 Message Providers

#### Configuring Providers

```php
// config/messenger.php
'providers' => [
    'twilio' => [
        'driver' => 'twilio',
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'capabilities' => ['sms', 'whatsapp'],
    ],
    
    'aws_sns' => [
        'driver' => 'aws_sns',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'capabilities' => ['sms'],
    ],
    
    'smsmisr' => [
        'driver' => 'smsmisr',
        'username' => env('SMSMISR_USERNAME'),
        'password' => env('SMSMISR_PASSWORD'),
        'sender' => env('SMSMISR_SENDER'),
        'capabilities' => ['sms', 'otp'],
    ],
],
```

#### Provider Selection in Actions

Users can select providers in both individual and bulk actions. The interface automatically shows available channels based on the selected provider.

### 📊 Analytics Dashboard

#### Adding to Filament Admin Panel

```php
// In your Filament AdminPanelProvider
use ihabrouk\Messenger\Livewire\MessengerAnalyticsDashboard;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            MessengerAnalyticsDashboard::class,
        ]);
}
```

#### Analytics Dashboard Features

- **Real-time Metrics**: Message delivery rates, failures, costs
- **Provider Comparison**: Performance across different providers
- **Cost Analysis**: Detailed cost breakdowns and trends
- **Campaign Tracking**: Individual campaign performance
- **Geographic Distribution**: Message distribution by region
- **Time-based Analysis**: Hourly, daily, weekly, monthly reports

### 🎨 Livewire Components

#### Channel Selector Component

```php
use ihabrouk\Messenger\Components\ChannelSelector;

// In a Livewire component or Blade view
<livewire:channel-selector 
    :providers="['twilio', 'aws_sns']"
    wire:model="selectedChannel"
/>
```

#### Template Selector Component

```php
use ihabrouk\Messenger\Components\TemplateSelector;

// In a Livewire component or Blade view
<livewire:template-selector 
    channel="sms"
    message-type="marketing"
    wire:model="selectedTemplate"
/>
```

### 🛠️ Advanced Usage

#### Custom Message Drivers

Create custom drivers for new providers:

```php
<?php

namespace App\Messenger\Drivers;

use ihabrouk\Messenger\Contracts\MessageProviderInterface;
use ihabrouk\Messenger\Data\SendMessageData;
use ihabrouk\Messenger\Data\MessageResponse;

class CustomSmsDriver implements MessageProviderInterface
{
    public function send(SendMessageData $data): MessageResponse
    {
        // Implement your custom provider logic
        
        return new MessageResponse(
            success: true,
            messageId: 'custom-' . uniqid(),
            cost: 0.05,
            provider: 'custom',
            channel: $data->channel
        );
    }

    public function getCapabilities(): array
    {
        return ['sms'];
    }
}
```

Register your custom driver:

```php
// In a service provider
use ihabrouk\Messenger\Services\ProviderRegistry;

public function boot()
{
    $registry = app(ProviderRegistry::class);
    $registry->register('custom', CustomSmsDriver::class, ['sms']);
}
```

#### Webhook Handling

The package automatically handles webhooks for delivery status updates:

```php
// Routes are automatically registered:
// POST /messenger/webhook/twilio
// POST /messenger/webhook/smsmisr

// Enable in config/messenger.php
'webhooks' => [
    'enabled' => true,
    'verify_signatures' => true,
    'auto_update_status' => true,
],
```

#### Queue Configuration

Configure queues for better performance:

```php
// config/messenger.php
'queue' => [
    'enabled' => true,
    'connection' => 'redis',
    'queue' => 'messages',
    'batch_queue' => 'bulk-messages',
    'retry_after' => 3600,
    'max_tries' => 3,
],
```

### 🔒 Security & GDPR

#### Consent Tracking

```php
use ihabrouk\Messenger\Models\Consent;

// Check consent before sending
$hasConsent = Consent::hasValidConsent('+1234567890', 'marketing');

if ($hasConsent) {
    // Send marketing message
}

// Automatic consent checking in actions
BulkMessageAction::make()
    ->checkConsent(true)  // Automatically filter out non-consented users
    ->consentType('marketing')
    ->before(function ($action, $records) {
        // Filter records based on consent
        $consentedRecords = $records->filter(function ($record) {
            return Consent::hasValidConsent($record->phone, 'marketing');
        });
        
        if ($consentedRecords->isEmpty()) {
            $action->halt();
            Notification::make()
                ->title('No Consented Recipients')
                ->body('No recipients have valid marketing consent.')
                ->warning()
                ->send();
        }
    });
```

#### Data Retention

```php
// Automatic cleanup command
php artisan messenger:cleanup-logs --days=365

// In config/messenger.php
'gdpr' => [
    'enabled' => true,
    'retention_days' => 365,
    'auto_cleanup' => true,
    'anonymize_on_deletion' => true,
],
```

### 🔧 Artisan Commands

The package includes several helpful commands:

```bash
# Test provider connections
php artisan messenger:test-provider twilio

# List available providers
php artisan messenger:list-providers

# Create a new message template
php artisan messenger:make-template welcome-sms

# Manage templates
php artisan messenger:manage-templates

# Send a test message
php artisan messenger:send-message "+1234567890" "Test message" --provider=twilio

# Validate template syntax
php artisan messenger:validate-template welcome-sms

# Preview template with variables
php artisan messenger:preview-template welcome-sms --name="John Doe"

# Process webhook (manual)
php artisan messenger:process-webhook twilio

# Cleanup old logs
php artisan messenger:cleanup-logs --days=30

# View system status
php artisan messenger:status

# Run automation rules
php artisan messenger:automation
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Test specific providers
php artisan messenger:test-provider twilio
php artisan messenger:test-provider aws_sns
```

### Mock Testing

The package includes a mock driver for testing:

```php
// In tests
use ihabrouk\Messenger\Testing\MessengerFake;

public function test_can_send_message()
{
    MessengerFake::fake();
    
    $response = app(MessengerService::class)->send([
        'to' => '+1234567890',
        'message' => 'Test message',
        'provider' => 'mocktest'
    ]);
    
    MessengerFake::assertSent(function ($message) {
        return $message->to === '+1234567890';
    });
}
```

## 📚 API Reference

### Core Services

#### MessengerService

```php
use ihabrouk\Messenger\Services\MessengerService;

$messenger = app(MessengerService::class);

// Send a message
$response = $messenger->send(SendMessageData $data);

// Send using template
$response = $messenger->sendTemplate(array $data);

// Send bulk messages
$batch = $messenger->sendBulk(BulkMessageData $data);
```

#### ConsentService

```php
use ihabrouk\Messenger\Services\ConsentService;

$consentService = app(ConsentService::class);

// Check consent
$hasConsent = $consentService->hasConsent($phone, $type);

// Process opt-in
$consent = $consentService->processOptIn($phone, $type);

// Process opt-out
$consentService->processOptOut($phone);
```

#### AnalyticsService

```php
use ihabrouk\Messenger\Services\AnalyticsService;

$analytics = app(AnalyticsService::class);

// Get delivery statistics
$stats = $analytics->getDeliveryStats($dateRange);

// Get provider performance
$performance = $analytics->getProviderPerformance($provider);

// Get cost analysis
$costs = $analytics->getCostAnalysis($period);
```

### Data Transfer Objects

#### SendMessageData

```php
use ihabrouk\Messenger\Data\SendMessageData;

$data = new SendMessageData(
    to: '+1234567890',
    message: 'Hello World',
    channel: 'sms',
    provider: 'twilio',
    templateId: null,
    variables: [],
    scheduledAt: null
);
```

#### MessageResponse

```php
use ihabrouk\Messenger\Data\MessageResponse;

$response = new MessageResponse(
    success: true,
    messageId: 'msg_123',
    cost: 0.05,
    provider: 'twilio',
    channel: 'sms',
    error: null
);
```

## 🔧 Configuration Reference

### Complete Configuration File

```php
<?php

return [
    // Default provider
    'default_provider' => env('MESSENGER_DEFAULT_PROVIDER', 'twilio'),
    
    // Provider configurations
    'providers' => [
        'twilio' => [
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'capabilities' => ['sms', 'whatsapp'],
            'cost_per_sms' => 0.0075,
            'webhook_enabled' => true,
        ],
        
        'aws_sns' => [
            'driver' => 'aws_sns',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'capabilities' => ['sms'],
            'cost_per_sms' => 0.00645,
        ],
        
        'smsmisr' => [
            'driver' => 'smsmisr',
            'username' => env('SMSMISR_USERNAME'),
            'password' => env('SMSMISR_PASSWORD'),
            'sender' => env('SMSMISR_SENDER'),
            'capabilities' => ['sms', 'otp'],
            'cost_per_sms' => 0.02,
        ],
    ],
    
    // Queue configuration
    'queue' => [
        'enabled' => env('MESSENGER_QUEUE_ENABLED', true),
        'connection' => env('MESSENGER_QUEUE_CONNECTION', 'redis'),
        'queue' => env('MESSENGER_QUEUE_NAME', 'messages'),
        'batch_queue' => env('MESSENGER_BATCH_QUEUE_NAME', 'bulk-messages'),
        'retry_after' => env('MESSENGER_RETRY_AFTER', 3600),
        'max_tries' => env('MESSENGER_MAX_TRIES', 3),
    ],
    
    // GDPR and consent management
    'gdpr' => [
        'enabled' => env('MESSENGER_GDPR_ENABLED', true),
        'consent_required' => env('MESSENGER_CONSENT_REQUIRED', true),
        'double_opt_in' => env('MESSENGER_DOUBLE_OPT_IN', true),
        'retention_days' => env('MESSENGER_RETENTION_DAYS', 365),
        'auto_cleanup' => env('MESSENGER_AUTO_CLEANUP', true),
        'anonymize_on_deletion' => true,
    ],
    
    // Analytics configuration
    'analytics' => [
        'enabled' => env('MESSENGER_ANALYTICS_ENABLED', true),
        'real_time' => env('MESSENGER_REAL_TIME_ANALYTICS', true),
        'track_costs' => true,
        'track_geography' => true,
        'dashboard_refresh_interval' => 30, // seconds
    ],
    
    // Caching
    'cache' => [
        'enabled' => env('MESSENGER_CACHE_ENABLED', true),
        'driver' => env('MESSENGER_CACHE_DRIVER', 'redis'),
        'ttl' => env('MESSENGER_CACHE_TTL', 3600),
        'prefix' => 'messenger:',
    ],
    
    // Rate limiting
    'rate_limiting' => [
        'enabled' => true,
        'per_minute' => 60,
        'per_hour' => 1000,
        'per_day' => 10000,
    ],
    
    // Webhooks
    'webhooks' => [
        'enabled' => env('MESSENGER_WEBHOOKS_ENABLED', true),
        'verify_signatures' => true,
        'auto_update_status' => true,
        'timeout' => 30,
    ],
    
    // Circuit breaker
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'recovery_timeout' => 300,
        'expected_exception_types' => [
            \ihabrouk\Messenger\Exceptions\ProviderException::class,
        ],
    ],
    
    // Monitoring
    'monitoring' => [
        'enabled' => true,
        'sentry_dsn' => env('SENTRY_DSN'),
        'alert_on_failure_rate' => 0.1, // 10%
        'alert_email' => env('MESSENGER_ALERT_EMAIL'),
    ],
];
```

## 🚀 Performance Optimization

### Redis Configuration

```php
// config/cache.php
'stores' => [
    'messenger' => [
        'driver' => 'redis',
        'connection' => 'messenger',
        'prefix' => 'messenger:cache:',
    ],
],

// config/database.php
'redis' => [
    'messenger' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_MESSENGER_DB', 2),
    ],
],
```

### Queue Workers

```bash
# Start queue workers for optimal performance
php artisan queue:work redis --queue=messages --sleep=3 --tries=3 --max-time=3600
php artisan queue:work redis --queue=bulk-messages --sleep=1 --tries=3 --max-time=7200

# Monitor queue status
php artisan queue:monitor redis:messages,redis:bulk-messages --max=100
```

### Database Indexing

```php
// Add these indexes for better performance
Schema::table('messenger_messages', function (Blueprint $table) {
    $table->index(['status', 'created_at']);
    $table->index(['provider', 'channel']);
    $table->index(['recipient_phone']);
    $table->index(['batch_id']);
});

Schema::table('messenger_consents', function (Blueprint $table) {
    $table->index(['phone', 'consent_type']);
    $table->index(['status', 'expires_at']);
});
```

## 📈 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/ihabrouk/messenger.git
cd messenger

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Set up testing database
php artisan migrate --database=testing

# Run tests
composer test
```

### Coding Standards

```bash
# Check code style
composer pint

# Run static analysis
composer phpstan

# Run all quality checks
composer check
```

## � Documentation

- [Installation Guide](docs/installation.md)
- [Configuration Reference](docs/configuration.md)
- [Provider Integration](docs/providers.md)
- [Template Management](docs/templates.md)
- [Analytics Dashboard](docs/analytics.md)
- [GDPR Compliance](docs/gdpr.md)
- [API Reference](docs/api.md)
- [Troubleshooting](docs/troubleshooting.md)

## �🔒 Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

### Security Features

- **Input Validation**: All inputs are validated and sanitized
- **Rate Limiting**: Built-in protection against abuse
- **Webhook Verification**: Signature verification for webhooks
- **Data Encryption**: Sensitive data is encrypted at rest
- **Audit Logging**: Complete audit trail for compliance
- **GDPR Compliance**: Built-in data protection features

## 🌟 Credits

- **Author**: [Ismail El Habrouk](https://github.com/ihabrouk)
- **Contributors**: [All Contributors](../../contributors)
- **Inspired by**: Laravel Notification system and Filament's extensibility

### Special Thanks

- Laravel team for the amazing framework
- Filament team for the beautiful admin panel
- Twilio, AWS, and SMS Misr for their reliable APIs
- The open-source community for inspiration and feedback

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

## 📞 Support

- **Documentation**: [Full Documentation](https://messenger-docs.example.com)
- **Issues**: [GitHub Issues](https://github.com/ihabrouk/messenger/issues)
- **Discussions**: [GitHub Discussions](https://github.com/ihabrouk/messenger/discussions)
- **Email**: [ismailhabrouk@gmail.com](mailto:ismailhabrouk@gmail.com)

### Enterprise Support

For enterprise customers, we offer:
- Priority support
- Custom integrations
- Performance optimization
- Training and onboarding
- SLA guarantees

Contact us for enterprise pricing and support options.

---

<div align="center">

**Built with ❤️ for the Laravel community**

[⭐ Star this repo](https://github.com/ihabrouk/messenger) | [🐛 Report Bug](https://github.com/ihabrouk/messenger/issues) | [💡 Request Feature](https://github.com/ihabrouk/messenger/issues/new?template=feature_request.md)

</div>
