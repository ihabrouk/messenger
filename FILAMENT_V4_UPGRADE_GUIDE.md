# Filament v4 Upgrade Guide

## For Projects Using the Messenger Package

If you're getting dependency conflicts when updating to the latest messenger package, you need to upgrade your main Laravel project to Filament v4.

### Step 1: Update Composer Requirements

In your main Laravel project's `composer.json`, update:

```json
{
  "require": {
    "filament/filament": "^4.0"
  }
}
```

### Step 2: Run Composer Update with Dependencies

```bash
composer require "filament/filament:^4.0" --with-all-dependencies
```

### Step 3: Run Filament v4 Upgrade Command

```bash
php artisan filament:upgrade
```

This command will automatically:
- Update your Filament configuration files
- Convert v3 syntax to v4 syntax in your resources
- Update imports and namespaces
- Handle most breaking changes automatically

### Step 4: Update Your Package

Now you can safely update the messenger package:

```bash
composer update ihabrouk/messenger
```

### Step 5: Test Your Application

After the upgrade:
1. Test your admin panels
2. Check all Filament resources, pages, and widgets
3. Verify custom components work correctly

## Manual Migration Notes

If you have custom Filament components, you may need to update:

- `Filament\Forms\Components\*` → remains the same
- Form schemas moved to `Filament\Schemas\*` in some cases
- Check the [official Filament v4 upgrade guide](https://filamentphp.com/docs/4.x/upgrade-guide) for details

## Benefits of Upgrading

- ✅ Better performance and stability
- ✅ New features and components
- ✅ Continued security updates
- ✅ Compatibility with latest Laravel versions
- ✅ Future-proof your application

## Need Help?

If you encounter issues during the upgrade, check:
1. Filament v4 documentation
2. The upgrade command output for specific guidance
3. Your custom Filament code for manual adjustments needed
