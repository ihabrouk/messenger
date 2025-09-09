# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-09-09

### Added
- Support for Laravel 12.28.1 (latest version)
- Support for Filament v4.0.8
- PHP 8.4 compatibility
- Filament v4 upgrade guide documentation
- Enhanced circuit breaker functionality
- Improved automation service with better error handling

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

## [0.1.0] - Previous Release
- Initial package release
- Basic messaging functionality
- SMS and email provider support
