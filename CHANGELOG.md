# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Version Support

- **v2.x**: Laravel 11+ with Filament v4+ (main branch)
- **v1.x**: Laravel 10/11 with Filament v3 (v1.x branch)

## [2.0.0] - 2025-09-09

### Added
- Support for Laravel 12.28.1 (latest version)
- Support for Filament v4.0.8
- PHP 8.4 compatibility
- Filament v4 upgrade guide documentation
- Enhanced circuit breaker functionality
- Improved automation service with better error handling
- BulkMessage backward compatibility model

### Changed
- **BREAKING:** Minimum Laravel version is now 11.0
- **BREAKING:** Minimum Filament version is now 4.0
- **BREAKING:** Minimum PHP version is now 8.2
- Updated to use Filament v4 schema components and utilities
- Modernized code to use latest Laravel features
- Improved composer dependencies for better compatibility

### Fixed
- MessengerException constructor parameter type issues
- AutomationService syntax errors and compilation issues
- DateInterval property access in automation workflows
- Circuit breaker service method compatibility
- Monitoring service method visibility issues
- BulkMessage model references updated to use Batch model
- MessageStatus enum constant references

### Removed
- Support for Laravel 10.x and below
- Support for Filament v3.x
- Support for PHP 8.1 and below

### Migration Guide
See `FILAMENT_V4_UPGRADE_GUIDE.md` for detailed upgrade instructions.

## [1.0.0] - 2025-09-09

### Added
- Complete Laravel messaging package implementation
- Support for Laravel 10.x and 11.x
- Support for Filament v3.0
- SMS providers: SMS Misr, Twilio
- WhatsApp messaging via Twilio
- Email messaging capabilities
- Template management system
- Consent management (GDPR compliant)
- Bulk messaging with Batch model
- Circuit breaker pattern for provider reliability
- Monitoring and analytics
- Webhook handling for delivery status
- Testing utilities and mock providers

### Requirements
- PHP 8.1+
- Laravel 10.x or 11.x  
- Filament v3.0+

## [0.1.0] - Previous Release
- Initial package release
- Basic messaging functionality
- SMS and email provider support
