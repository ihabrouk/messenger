# Messenger Package Installation Guide

## Installation

Install the package via Composer:

```bash
composer require ihabrouk/messenger
```

## Configuration

1. Publish the config file:
```bash
php artisan vendor:publish --tag=messenger-config
```

2. Run the migrations:
```bash
php artisan migrate
```

3. Configure your messaging providers in `config/messenger.php`

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
