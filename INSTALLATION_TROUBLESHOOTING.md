# Package Installation Troubleshooting

## Issue: "Class Ihabrouk\Messenger\Models\Batch not found"

This error typically occurs when the package is not properly installed or the autoloader needs to be refreshed.

## Solution Steps:

### 1. Verify Package Installation
```bash
composer require ihabrouk/messenger
```

### 2. Clear and Regenerate Autoloader
```bash
composer dump-autoload
```

### 3. Publish Package Assets (Required for models to work with database)
```bash
# Publish configuration
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-config"

# Publish migrations (REQUIRED - models won't work without database tables)
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-migrations"

# Run migrations
php artisan migrate
```

### 4. Optional: Publish Views and Language Files
```bash
# Publish views (if you need to customize them)
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-views"

# Publish language files (if you need to customize them)
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-lang"
```

### 5. Clear Application Cache
```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan cache:clear
```

## Common Issues and Solutions:

### Issue 1: "No publishable resources for tag [messenger-migrations]"
This means the migration files aren't being found. Try these solutions:

**Solution A - Refresh autoloader:**
```bash
composer dump-autoload
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-migrations"
```

**Solution B - Publish all package assets:**
```bash
php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider"
```

**Solution C - Use force flag:**
```bash
php artisan vendor:publish --tag="messenger-migrations" --force
```

### Issue 2: Service Provider Not Registered
If auto-discovery doesn't work, manually add to `config/app.php`:
```php
'providers' => [
    // ...
    Ihabrouk\Messenger\Providers\MessengerServiceProvider::class,
],

'aliases' => [
    // ...
    'Messenger' => Ihabrouk\Messenger\Facades\Messenger::class,
],
```

### Issue 3: Database Tables Don't Exist
The models require database tables. Make sure you've published and run migrations:
```bash
php artisan vendor:publish --tag="messenger-migrations"
php artisan migrate
```

### Issue 4: Namespace Issues
Ensure you're using the correct namespace:
```php
use Ihabrouk\Messenger\Models\Batch;
use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Models\Template;
```

### Issue 5: Composer Autoload Cache
Sometimes composer's autoload cache gets corrupted:
```bash
composer dump-autoload --optimize
```

## Verification
To verify the installation worked, run this in `php artisan tinker`:
```php
// Check if classes exist
class_exists('Ihabrouk\Messenger\Models\Batch');
class_exists('Ihabrouk\Messenger\Services\MessengerService');

// Try creating a test instance (will fail if migrations weren't run)
$batch = new \Ihabrouk\Messenger\Models\Batch();
```
