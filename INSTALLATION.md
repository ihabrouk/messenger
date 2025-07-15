# Installation Guide for Laravel Messenger Package

This guide will walk you through installing and setting up the Laravel Messenger package in your application.

## Prerequisites

- PHP 8.2 or higher
- Laravel 11.0 or 12.0
- FilamentPHP 3.2 or higher
- Redis (recommended for queues and caching)

## Step 1: Install the Package

```bash
composer require ihabrouk/messenger
```

## Step 2: Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="messenger-config"
```

This will create `config/messenger.php` with all the package settings.

## Step 3: Publish and Run Migrations

Publish the migrations:

```bash
php artisan vendor:publish --tag="messenger-migrations"
```

Run the migrations:

```bash
php artisan migrate
```

This will create the following tables:
- `messenger_messages` - Store all sent messages
- `messenger_templates` - Message templates
- `messenger_batches` - Bulk message batches
- `messenger_logs` - Activity logs
- `messenger_webhooks` - Webhook delivery tracking
- `messenger_contacts` - Contact management
- `messenger_consents` - GDPR consent tracking

## Step 4: Environment Configuration

Add these variables to your `.env` file:

### Basic Configuration
```bash
# Default Provider
MESSENGER_DEFAULT_PROVIDER=smsmisr

# Queue Configuration (recommended)
MESSENGER_QUEUE_CONNECTION=redis
MESSENGER_QUEUE_NAME=messenger

# Analytics
MESSENGER_ANALYTICS_ENABLED=true
```

### SMS Misr Configuration
```bash
SMS_MISR_API_USERNAME=your_username
SMS_MISR_API_PASSWORD=your_password
SMS_MISR_SENDER_ID=your_sender_id
SMS_MISR_ENVIRONMENT=2  # 1 for Live, 2 for Test
SMS_MISR_WEBHOOK_SECRET=your_webhook_secret
```

### Twilio Configuration
```bash
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=your_phone_number
TWILIO_WEBHOOK_SECRET=your_webhook_secret
```

## Step 5: Configure Queue Workers

Since the package uses queues for message processing, set up queue workers:

```bash
# Start queue workers
php artisan queue:work redis --queue=urgent,high,default,low,bulk,scheduled,retries,webhooks
```

For production, use Supervisor or Laravel Horizon to manage queue workers.

## Step 6: Configure Webhooks (Optional)

For delivery tracking, configure webhooks with your providers:

### SMS Misr Webhook URL:
```
https://your-domain.com/messenger/webhook/smsmisr
```

### Twilio Webhook URL:
```
https://your-domain.com/messenger/webhook/twilio
```

## Step 7: Publish Views and Language Files (Optional)

If you want to customize views or translations:

```bash
# Publish views
php artisan vendor:publish --tag="messenger-views"

# Publish language files
php artisan vendor:publish --tag="messenger-lang"
```

## Step 8: Basic Usage Test

Test the installation:

```php
use Ihabrouk\Messenger\Facades\Messenger;

// Simple test message
Messenger::send([
    'recipient_phone' => '+1234567890',
    'content' => 'Test message from Laravel Messenger!',
    'provider' => 'mocktest' // Use mocktest for development
]);
```

## Step 9: FilamentPHP Integration

If using FilamentPHP, add the Messenger components to your admin panel:

### Add to your Filament Admin Panel Provider:

```php
use Ihabrouk\Messenger\Resources\MessageResource;
use Ihabrouk\Messenger\Resources\TemplateResource;
use Ihabrouk\Messenger\Resources\BatchResource;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... your existing configuration
        ->resources([
            MessageResource::class,
            TemplateResource::class,
            BatchResource::class,
            // ... your other resources
        ]);
}
```

### Add Actions to Your Existing Resources:

```php
use Ihabrouk\Messenger\Actions\SendMessageAction;

// In your table() method
public function table(Table $table): Table
{
    return $table
        ->actions([
            SendMessageAction::make()
                ->phoneColumn('phone_number')
                ->nameColumn('full_name'),
            // ... your other actions
        ]);
}
```

## Step 10: Advanced Configuration

### Circuit Breaker Settings:
```bash
MESSENGER_CIRCUIT_BREAKER_THRESHOLD=5
MESSENGER_CIRCUIT_BREAKER_TIMEOUT=300
```

### Rate Limiting:
```bash
MESSENGER_RATE_LIMIT_ENABLED=true
MESSENGER_RATE_LIMIT_MAX_ATTEMPTS=100
MESSENGER_RATE_LIMIT_DECAY_MINUTES=60
```

### Monitoring:
```bash
MESSENGER_MONITORING_ENABLED=true
MESSENGER_ERROR_TRACKING_ENABLED=true
```

## Verification Commands

Test your installation with these Artisan commands:

```bash
# Test provider connection
php artisan messenger:test-provider smsmisr

# List available providers
php artisan messenger:list-providers

# Check system status
php artisan messenger:status
```

## Troubleshooting

### Common Issues:

1. **Queue Jobs Not Processing:**
   - Ensure queue workers are running
   - Check Redis connection
   - Verify queue configuration in `config/queue.php`

2. **Provider Authentication Errors:**
   - Verify API credentials in `.env`
   - Check provider-specific configuration
   - Test with mock provider first

3. **Webhook Delivery Issues:**
   - Ensure webhook URLs are accessible
   - Check webhook signatures
   - Verify SSL certificates

4. **Database Migration Errors:**
   - Check database permissions
   - Ensure Laravel is up to date
   - Run migrations with `--force` if needed

### Debug Mode:

Enable debug mode for troubleshooting:

```bash
MESSENGER_DEBUG_MODE=true
LOG_LEVEL=debug
```

## Next Steps

1. **Create Templates:** Set up message templates for common use cases
2. **Configure Automation:** Set up triggered messages
3. **Monitor Analytics:** Use the built-in analytics dashboard
4. **Customize UI:** Modify views and components as needed
5. **Scale Up:** Configure multiple providers and load balancing

## Support

For support and questions:
- Check the documentation in the `docs/` folder
- Review the example implementations in `Demo/`
- Open an issue on GitHub
- Contact support at support@example.com

## Security Considerations

- Always use HTTPS for webhook endpoints
- Secure your API credentials
- Enable consent management for GDPR compliance
- Configure rate limiting for production use
- Regular backup of message data
- Monitor for unusual activity

Your Laravel Messenger package is now ready to use! 🚀
