# Release Notes Summary

## 🎉 Version 2.0.2 (Latest - Recommended)
**Release Date:** September 9, 2025  
**Target:** Modern Laravel/Filament projects

### 🐛 **Critical Fix**
- **FIXED:** Method names in documentation and examples
  - `phoneColumn()` → `phoneField()` ✅ 
  - `nameColumn()` → `nameField()` ✅
- Updated all documentation with correct method names
- Fixed examples to prevent "Method does not exist" errors

### 📦 Installation
```bash
composer require "ihabrouk/messenger:^2.0"
```

### 🔧 Migration from Previous Docs
If you followed our previous documentation, update your code:
```php
// OLD (from incorrect docs)
SendMessageAction::make()
    ->phoneColumn('phone')
    ->nameColumn('name')

// NEW (correct)
SendMessageAction::make()
    ->phoneField('phone') 
    ->nameField('name')
```

### 🔧 Requirements
- Laravel 11.0+
- Filament 4.0+
- PHP 8.2+

---

## 🎉 Version 2.0.1
**Release Date:** September 9, 2025

### ✨ What's New
- Laravel 12.28.1 support (latest version)
- Filament v4.0.8 compatibility with new schema components
- PHP 8.2+ requirement with modern features
- Enhanced circuit breaker and automation services
- Backward compatibility BulkMessage model
- Comprehensive documentation and migration guides

### 📦 Installation
```bash
composer require "ihabrouk/messenger:^2.0"
```

### 🔧 Requirements
- Laravel 11.0+
- Filament 4.0+
- PHP 8.2+

---

## 🛠️ Version 1.0.0 (Maintenance)
**Release Date:** September 9, 2025  
**Target:** Stable production systems

### ✨ Features
- Complete messaging package with all core features
- Laravel 10.x and 11.x support
- Filament v3.x compatibility
- Stable API with no breaking changes
- Production-ready with full feature set

### 📦 Installation
```bash
composer require "ihabrouk/messenger:^1.0"
```

### 🔧 Requirements
- Laravel 10.0 - 11.x
- Filament 3.0+
- PHP 8.1+

---

## 🚀 Migration Between Versions

### From v1.x to v2.x
1. **Upgrade Laravel to 11+**
   ```bash
   composer require "laravel/framework:^11.0" --with-all-dependencies
   ```

2. **Upgrade Filament to v4**
   ```bash
   composer require "filament/filament:^4.0" --with-all-dependencies
   php artisan filament:upgrade
   ```

3. **Upgrade Messenger package**
   ```bash
   composer require "ihabrouk/messenger:^2.0"
   ```

4. **Run migrations if any**
   ```bash
   php artisan migrate
   ```

### Backward Compatibility
- v2.x includes a `BulkMessage` model that extends `Batch` for compatibility
- Existing code using `Batch` model works in both versions
- All public APIs remain stable between versions

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [README.md](README.md) | Main package documentation |
| [VERSION_SUPPORT.md](VERSION_SUPPORT.md) | Comprehensive version guide |
| [CHANGELOG.md](CHANGELOG.md) | Complete change history |
| [FILAMENT_V4_UPGRADE_GUIDE.md](FILAMENT_V4_UPGRADE_GUIDE.md) | Filament upgrade guide |

---

## 🆘 Getting Help

### For v2.x (Current)
- ✅ Active development and support
- ✅ New features and improvements
- ✅ Full documentation and examples

### For v1.x (Maintenance)
- 🔧 Critical bug fixes only
- 🛡️ Security patches
- ❌ No new features

### Support Channels
- **GitHub Issues:** Use version tags (`v1.x` or `v2.x`)
- **Documentation:** Version-specific guides available
- **Migration Help:** Detailed guides and scripts provided

---

## 🎯 Which Version Should I Use?

### Choose v2.x if:
- ✅ Starting a new project
- ✅ Using Laravel 11+
- ✅ Can upgrade to Filament v4
- ✅ Want latest features

### Choose v1.x if:
- ✅ Existing Laravel 10 project
- ✅ Using Filament v3
- ✅ Need stable API
- ✅ Planning gradual migration

---

*For the most up-to-date information, see the [VERSION_SUPPORT.md](VERSION_SUPPORT.md) documentation.*
