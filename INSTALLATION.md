# Messenger Package Installation Guide

## Installation

Install the package via Composer:

```bash
composer require ihabrouk/messenger
```

## Required Setup Steps

### 1. Publish and Run Migrations (REQUIRED)
The package models require database tables to function:

```bash
# Publish migrations
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-migrations"

# Run migrations to create required tables
php artisan migrate
```

**Alternative if the above doesn't work:**
```bash
# Publish all package assets
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider"

# Or copy migrations manually (if needed)
php artisan vendor:publish --tag="messenger-migrations" --force
```

### 2. Publish Configuration
```bash
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-config"
```

### 3. Configure Providers
Edit `config/messenger.php` to configure your messaging providers (Twilio, SMS Misr, etc.)

## Optional Setup

### Publish Views (if you plan to customize them)
```bash
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-views"
```

### Publish Language Files (for localization)
```bash
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-lang"
```

## Verification

To verify the installation worked correctly, run in `php artisan tinker`:
```php
// Check if models are available
\Ihabrouk\Messenger\Models\Batch::count();
\Ihabrouk\Messenger\Models\Message::count();
```

## Troubleshooting

If you encounter "Class not found" errors, see [INSTALLATION_TROUBLESHOOTING.md](INSTALLATION_TROUBLESHOOTING.md) for detailed solutions.

## Usage

```php
use Ihabrouk\Messenger\Facades\Messenger;

// Send an SMS
Messenger::sms()
    ->to('+1234567890')
    ->message('Hello World!')
    ->send();

// Send with template
Messenger::sms()
    ->to('+1234567890')
    ->template('welcome', ['name' => 'John'])
    ->send();
```

## Available Commands

```bash
# List available providers
php artisan messenger:list-providers

# Test a provider
php artisan messenger:test-provider {provider}

# Send a message
php artisan messenger:send {provider} {to} {message}
```

## Testing

```bash
composer test
```
