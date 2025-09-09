# Version Support & Migration Guide

This document explains the versioning strategy for the Laravel Messenger package and how to choose the right version for your project.

## Version Overview

| Version | Branch | Laravel | Filament | PHP | Status | End of Life |
|---------|--------|---------|----------|-----|--------|-------------|
| **2.x** | `main` | 11.0+ | 4.0+ | 8.2+ | ✅ Active | TBD |
| **1.x** | `v1.x` | 10.0-11.x | 3.0+ | 8.1+ | 🔧 Maintenance | 2026-09-09 |
| **0.x** | - | - | - | - | ❌ Deprecated | 2025-09-09 |

## Installation Commands

### For New Projects (Recommended)
```bash
# Latest stable version with modern features
composer require "ihabrouk/messenger:^2.0"
```

### For Existing Projects on Laravel 10 or Filament v3
```bash
# Stable v1.x for legacy compatibility
composer require "ihabrouk/messenger:^1.0"
```

### For Development/Testing
```bash
# Latest development version
composer require "ihabrouk/messenger:dev-main"

# v1.x development branch
composer require "ihabrouk/messenger:dev-v1.x"
```

## Version Differences

### Version 2.x (Current)
**Target: Modern Laravel/Filament projects**

- ✅ Laravel 11.0+ and 12.x support
- ✅ Filament v4.0+ with new schema components
- ✅ PHP 8.2+ with modern features
- ✅ Enhanced circuit breaker patterns
- ✅ Improved automation service
- ✅ Backward compatibility model (BulkMessage)
- ✅ Active development and new features

### Version 1.x (Maintenance)
**Target: Stable production systems**

- ✅ Laravel 10.x and 11.x support
- ✅ Filament v3.x compatibility
- ✅ PHP 8.1+ support
- ✅ Stable API with no breaking changes
- ✅ Security updates and critical bug fixes
- ❌ No new features (maintenance only)

## Migration Paths

### From v0.x to v1.x
1. Update composer requirement: `"ihabrouk/messenger:^1.0"`
2. Run `composer update`
3. No code changes required (stable API)

### From v1.x to v2.x
1. Upgrade Laravel to 11.0+
2. Upgrade Filament to v4.0+
3. Update PHP to 8.2+
4. Update composer requirement: `"ihabrouk/messenger:^2.0"`
5. Run upgrade process (see FILAMENT_V4_UPGRADE_GUIDE.md)

#### Quick Migration Script
```bash
#!/bin/bash
# Upgrade script for v1 to v2

# 1. Update Laravel to 11+
composer require "laravel/framework:^11.0" --with-all-dependencies

# 2. Update Filament to v4
composer require "filament/filament:^4.0" --with-all-dependencies
php artisan filament:upgrade

# 3. Update Messenger to v2
composer require "ihabrouk/messenger:^2.0"

# 4. Run any new migrations
php artisan migrate

echo "✅ Migration complete! Test your application thoroughly."
```

## Compatibility Matrix

### Model Compatibility
| Model Name | v1.x | v2.x | Notes |
|------------|------|------|-------|
| `Batch` | ✅ | ✅ | Primary bulk messaging model |
| `BulkMessage` | ❌ | ✅ | Backward compatibility alias in v2.x |
| `Message` | ✅ | ✅ | Individual message model |
| `Template` | ✅ | ✅ | Message templates |

### Feature Compatibility
| Feature | v1.x | v2.x | Notes |
|---------|------|------|-------|
| SMS Providers | ✅ | ✅ | SMS Misr, Twilio |
| WhatsApp | ✅ | ✅ | Via Twilio |
| Templates | ✅ | ✅ | Dynamic templates |
| Bulk Messaging | ✅ | ✅ | Batch processing |
| Circuit Breaker | ✅ | ✅ | Enhanced in v2.x |
| Analytics | ✅ | ✅ | Delivery tracking |
| Filament Actions | ✅ | ✅ | Updated syntax in v2.x |

## Choosing Your Version

### Choose v2.x if:
- ✅ Starting a new project
- ✅ Using Laravel 11+ 
- ✅ Can upgrade to Filament v4
- ✅ Want latest features and improvements
- ✅ Need long-term support

### Choose v1.x if:
- ✅ Existing production system on Laravel 10
- ✅ Using Filament v3 and can't upgrade yet
- ✅ Need stable API without breaking changes
- ✅ Planning gradual migration to v2.x later

## Support Policy

### Version 2.x (Active Development)
- 🚀 New features and improvements
- 🔧 Bug fixes and security patches
- 📚 Documentation updates
- 💬 Community support

### Version 1.x (Maintenance Mode)
- ❌ No new features
- 🔧 Critical bug fixes only
- 🛡️ Security patches
- 📞 Limited support (critical issues only)

## Getting Help

### For v2.x Issues
- GitHub Issues: Tag with `v2.x`
- Documentation: Latest docs apply to v2.x
- Discord: #messenger-v2 channel

### For v1.x Issues
- GitHub Issues: Tag with `v1.x`
- Documentation: Use v1.x branch docs
- Discord: #messenger-v1 channel

### Migration Help
- Discord: #migration-help channel
- GitHub Discussions: Migration category
- Professional Support: Contact for paid consulting

## Roadmap

### v2.1 (Planned - Q4 2025)
- Laravel 13 support when released
- New messaging providers
- Enhanced analytics dashboard
- Performance improvements

### v1.1 (If needed)
- Security patches only
- Critical bug fixes
- No new features

### v3.0 (Future - 2026)
- Breaking changes if needed
- PHP 8.3+ requirement
- Modern architecture improvements

## FAQ

**Q: Can I use both versions in the same project?**
A: No, choose one version per project to avoid conflicts.

**Q: How long will v1.x be supported?**
A: Until September 2026 for security patches only.

**Q: Is there a migration tool?**
A: Filament provides `php artisan filament:upgrade` for v3→v4. See our migration guide for the complete process.

**Q: What about the BulkMessage model?**
A: In v2.x, BulkMessage is a compatibility alias for Batch. Use Batch directly for new code.

**Q: Can I test v2.x before migrating?**
A: Yes, install on a separate branch/environment first: `composer require "ihabrouk/messenger:^2.0"`
