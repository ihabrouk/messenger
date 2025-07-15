# Package Testing & Removal Guide

This guide shows you how to test the package independently and remove the local version to test it as a true external package.

## Step 1: Test Package Locally First

Before publishing, test the package structure:

```bash
cd /path/to/messenger/package
composer install
```

## Step 2: Remove Local Messenger Implementation

To test the package as an external dependency, you need to remove the current local implementation:

### 2.1 Backup Current Implementation (Optional)
```bash
# Create backup
cp -r app/Messenger app/Messenger_backup_$(date +%Y%m%d)
```

### 2.2 Remove Local Files
```bash
# Remove the local messenger implementation
rm -rf app/Messenger

# Remove config file (will be replaced by package)
rm config/messenger.php

# Remove any published files that might conflict
rm -rf resources/views/components/messenger
rm -rf resources/lang/en/messenger.php
```

### 2.3 Update Service Provider Registration

Remove from `bootstrap/providers.php`:
```php
// Remove this line:
App\Messenger\Providers\MessengerServiceProvider::class,
```

### 2.4 Update Imports in Existing Code

Update any imports in your existing code from:
```php
use App\Messenger\...
```

To:
```php
use Ihabrouk\Messenger\...
```

Common files to update:
- Filament resources that use messenger actions
- Controllers using messenger services
- Models with messenger relationships
- Jobs/Commands using messenger

## Step 3: Install Package from Local Repository

### 3.1 Add Local Repository to Composer

Add to your main project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./app/Messenger"
        }
    ],
    "require": {
        "ihabrouk/messenger": "*"
    }
}
```

### 3.2 Install the Package
```bash
composer require ihabrouk/messenger
```

## Step 4: Follow Installation Guide

Now follow the installation steps from `INSTALLATION.md`:

```bash
# Publish config
php artisan vendor:publish --tag="messenger-config"

# Publish migrations
php artisan vendor:publish --tag="messenger-migrations"

# Run migrations
php artisan migrate

# Publish other assets if needed
php artisan vendor:publish --tag="messenger-views"
php artisan vendor:publish --tag="messenger-lang"
```

## Step 5: Test Package Functionality

Test the package works correctly:

```bash
# Test provider connectivity
php artisan messenger:test-provider mocktest

# List available providers
php artisan messenger:list-providers

# Check system status
php artisan messenger:status
```

### 5.1 Test Basic Functionality

Create a test route or command:

```php
// routes/web.php or in a command
use Ihabrouk\Messenger\Facades\Messenger;

Route::get('/test-messenger', function () {
    try {
        $result = Messenger::send([
            'recipient_phone' => '+1234567890',
            'content' => 'Test message from package!',
            'provider' => 'mocktest'
        ]);
        
        return response()->json([
            'status' => 'success',
            'result' => $result
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
});
```

### 5.2 Test FilamentPHP Integration

Add to your Filament panel:

```php
use Ihabrouk\Messenger\Resources\MessageResource;
use Ihabrouk\Messenger\Resources\TemplateResource;
use Ihabrouk\Messenger\Actions\SendMessageAction;

// In your panel provider
->resources([
    MessageResource::class,
    TemplateResource::class,
])

// In your existing resources, add actions
SendMessageAction::make()
    ->phoneColumn('phone_number')
    ->nameColumn('name')
```

## Step 6: Create GitHub Repository

### 6.1 Initialize Git Repository
```bash
cd /path/to/messenger/package
git init
git add .
git commit -m "Initial messenger package release"
```

### 6.2 Create GitHub Repository
1. Go to GitHub and create new repository: `messenger`
2. Don't initialize with README (already exists)

### 6.3 Push to GitHub
```bash
git remote add origin https://github.com/ihabrouk/messenger.git
git branch -M main
git push -u origin main
```

## Step 7: Set Up Packagist

### 7.1 Submit to Packagist
1. Go to [Packagist.org](https://packagist.org)
2. Sign in with GitHub
3. Click "Submit"
4. Enter repository URL: `https://github.com/ihabrouk/messenger`
5. Click "Check"
6. If validation passes, click "Submit"

### 7.2 Set Up Auto-Updates
1. In your GitHub repository settings
2. Go to Webhooks
3. Add webhook URL from Packagist
4. Set content type to `application/json`
5. Enable webhook

## Step 8: Test External Package Installation

### 8.1 Update Composer Configuration

Remove the local repository from `composer.json`:

```json
{
    "require": {
        "ihabrouk/messenger": "^1.0"
    }
}
```

### 8.2 Reinstall from Packagist
```bash
# Remove package
composer remove ihabrouk/messenger

# Clear composer cache
composer clear-cache

# Install from Packagist
composer require ihabrouk/messenger
```

## Step 9: Version Tagging

### 9.1 Tag First Release
```bash
git tag -a v1.0.0 -m "First stable release"
git push origin v1.0.0
```

### 9.2 For Future Updates
```bash
# Make changes
git add .
git commit -m "Update: describe changes"

# Tag new version
git tag -a v1.0.1 -m "Bug fixes and improvements"
git push origin main
git push origin v1.0.1
```

## Troubleshooting

### Common Issues:

1. **Namespace conflicts:**
   - Ensure all `App\Messenger` references are updated
   - Clear Laravel caches: `php artisan optimize:clear`

2. **Migration conflicts:**
   - Drop existing messenger tables if needed
   - Republish migrations

3. **Autoloader issues:**
   - Run `composer dump-autoload`
   - Clear Laravel caches

4. **Configuration not found:**
   - Republish config: `php artisan vendor:publish --tag="messenger-config"`
   - Check config cache: `php artisan config:clear`

### Validation Checklist:

- [ ] Package installs successfully
- [ ] Config publishes correctly
- [ ] Migrations run without errors
- [ ] Basic messaging works
- [ ] FilamentPHP integration works
- [ ] All commands execute successfully
- [ ] Webhooks receive data properly
- [ ] Queue jobs process correctly

## Success Criteria

The package is working correctly when:

1. ✅ `composer require ihabrouk/messenger` installs successfully
2. ✅ All artisan commands work
3. ✅ Messages can be sent through all providers
4. ✅ FilamentPHP admin interface is accessible
5. ✅ Webhooks process delivery updates
6. ✅ Queue jobs execute without errors
7. ✅ Analytics data is collected
8. ✅ All tests pass

Once all these criteria are met, your package is ready for production use! 🎉
