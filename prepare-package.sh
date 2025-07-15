#!/bin/bash

# Script to convert App\Messenger to standalone package
# This script copies files to src/ and updates namespaces

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
MESSENGER_DIR="$SCRIPT_DIR"

echo "🚀 Converting Messenger to standalone package..."

# Create src directory structure
echo "📁 Creating directory structure..."
mkdir -p src/{Actions,Commands,Components,Contracts,Data,Database/{Factories,Seeders,migrations},Demo,Drivers,Enums,Events,Exceptions,Http/Controllers,Jobs,Listeners,Livewire,Models,Providers,Resources,Services,Testing,Traits}

# Copy all files except composer.json, README.md, and package scripts
echo "📋 Copying files..."

# Copy Actions
cp -r Actions/* src/Actions/ 2>/dev/null || true

# Copy Commands  
cp -r Commands/* src/Commands/ 2>/dev/null || true

# Copy Components
cp -r Components/* src/Components/ 2>/dev/null || true

# Copy Contracts
cp -r Contracts/* src/Contracts/ 2>/dev/null || true

# Copy Data
cp -r Data/* src/Data/ 2>/dev/null || true

# Copy Database
cp -r Database/* src/Database/ 2>/dev/null || true

# Copy Demo
cp -r Demo/* src/Demo/ 2>/dev/null || true

# Copy Drivers
cp -r Drivers/* src/Drivers/ 2>/dev/null || true

# Copy Enums
cp -r Enums/* src/Enums/ 2>/dev/null || true

# Copy Events
cp -r Events/* src/Events/ 2>/dev/null || true

# Copy Exceptions
cp -r Exceptions/* src/Exceptions/ 2>/dev/null || true

# Copy Http
cp -r Http/* src/Http/ 2>/dev/null || true

# Copy Jobs
cp -r Jobs/* src/Jobs/ 2>/dev/null || true

# Copy Listeners
cp -r Listeners/* src/Listeners/ 2>/dev/null || true

# Copy Livewire
cp -r Livewire/* src/Livewire/ 2>/dev/null || true

# Copy Models
cp -r Models/* src/Models/ 2>/dev/null || true

# Copy Providers
cp -r Providers/* src/Providers/ 2>/dev/null || true

# Copy Resources
cp -r Resources/* src/Resources/ 2>/dev/null || true

# Copy Services
cp -r Services/* src/Services/ 2>/dev/null || true

# Copy Testing
cp -r Testing/* src/Testing/ 2>/dev/null || true

# Copy Traits
cp -r Traits/* src/Traits/ 2>/dev/null || true

# Copy docs
cp -r docs src/ 2>/dev/null || true

echo "🔄 Updating namespaces..."

# Update namespaces in all PHP files
find src -name "*.php" -type f -exec sed -i '' 's/namespace App\\Messenger/namespace Ihabrouk\\Messenger/g' {} \;
find src -name "*.php" -type f -exec sed -i '' 's/use App\\Messenger/use Ihabrouk\\Messenger/g' {} \;
find src -name "*.php" -type f -exec sed -i '' 's/App\\Messenger\\/Ihabrouk\\Messenger\\/g' {} \;

# Update specific service provider references
find src -name "*.php" -type f -exec sed -i '' 's/\\App\\Messenger\\Providers\\MessengerServiceProvider/\\Ihabrouk\\Messenger\\MessengerServiceProvider/g' {} \;

# Update config references to use the package namespace
find src -name "*.php" -type f -exec sed -i '' 's/__DIR__ \. \/\.\.\//config\/messenger\.php/__DIR__ \. \/\.\.\/config\/messenger\.php/g' {} \;

echo "📝 Creating package-specific files..."

# Create the main service provider in root of src
cat > src/MessengerServiceProvider.php << 'EOF'
<?php

namespace Ihabrouk\Messenger;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Ihabrouk\Messenger\Contracts\MessengerServiceInterface;
use Ihabrouk\Messenger\Contracts\TemplateServiceInterface;
use Ihabrouk\Messenger\Services\MessengerService;
use Ihabrouk\Messenger\Services\TemplateService;
use Ihabrouk\Messenger\Services\MessageProviderFactory;

class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/messenger.php',
            'messenger'
        );

        // Register provider registry
        $this->app->singleton(\Ihabrouk\Messenger\Contracts\ProviderRegistryInterface::class, \Ihabrouk\Messenger\Services\ProviderRegistry::class);

        // Register core services
        $this->app->singleton(MessengerServiceInterface::class, MessengerService::class);
        $this->app->singleton(TemplateServiceInterface::class, TemplateService::class);
        $this->app->singleton(\Ihabrouk\Messenger\Services\ProviderService::class);

        // Register template services
        $this->app->singleton(\Ihabrouk\Messenger\Services\TemplateManager::class);
        $this->app->singleton(\Ihabrouk\Messenger\Services\TemplateValidator::class);

        // Register new Phase 6 services
        $this->app->singleton(\Ihabrouk\Messenger\Services\DeliveryTrackingService::class);
        $this->app->singleton(\Ihabrouk\Messenger\Services\CircuitBreakerService::class);
        $this->app->singleton(\Ihabrouk\Messenger\Services\BulkMessageService::class);
        $this->app->singleton(\Ihabrouk\Messenger\Services\MonitoringService::class);
        $this->app->singleton(\Ihabrouk\Messenger\Services\AutomationService::class);

        // Register new Phase 7 services
        $this->app->singleton(\Ihabrouk\Messenger\Services\ConsentService::class);
        $this->app->singleton(\Ihabrouk\Messenger\Services\AnalyticsService::class);

        // Register factory with registry
        $this->app->singleton(MessageProviderFactory::class, function ($app) {
            return new MessageProviderFactory(
                $app,
                $app->make(\Ihabrouk\Messenger\Contracts\ProviderRegistryInterface::class),
                config('messenger.providers', [])
            );
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Ihabrouk\Messenger\Commands\MakeDriverCommand::class,
                \Ihabrouk\Messenger\Commands\MakeTemplateCommand::class,
                \Ihabrouk\Messenger\Commands\TestProviderCommand::class,
                \Ihabrouk\Messenger\Commands\ListProvidersCommand::class,
                \Ihabrouk\Messenger\Commands\ProcessWebhookCommand::class,
                \Ihabrouk\Messenger\Commands\CleanupLogsCommand::class,
                \Ihabrouk\Messenger\Commands\MessengerAutomationCommand::class,
                \Ihabrouk\Messenger\Commands\MessengerStatusCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/messenger.php' => config_path('messenger.php'),
            ], 'messenger-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/Database/migrations/' => database_path('migrations'),
            ], 'messenger-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views/components/messenger/' => resource_path('views/components/messenger'),
            ], 'messenger-views');

            // Publish language files
            $this->publishes([
                __DIR__ . '/../lang/en/messenger.php' => lang_path('en/messenger.php'),
            ], 'messenger-lang');
        }

        // Load migrations from package
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'messenger');

        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'messenger');

        // Auto-discover and register providers
        $registry = $this->app->make(\Ihabrouk\Messenger\Contracts\ProviderRegistryInterface::class);

        // Manually register built-in providers with capabilities
        $registry->register('smsmisr', \Ihabrouk\Messenger\Drivers\SmsMisrDriver::class, ['sms', 'otp', 'bulk_messaging']);
        $registry->register('twilio', \Ihabrouk\Messenger\Drivers\TwilioDriver::class, ['sms', 'whatsapp', 'bulk_messaging']);

        // Register test provider to verify plugin architecture
        $registry->register('mocktest', \Ihabrouk\Messenger\Drivers\MockTestDriver::class, ['sms', 'bulk_messaging']);

        // Register routes
        $this->registerRoutes();

        // Register event listeners
        $this->registerEventListeners();

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Ihabrouk\Messenger\Commands\SendMessageCommand::class,
                \Ihabrouk\Messenger\Commands\ValidateTemplateCommand::class,
                \Ihabrouk\Messenger\Commands\PreviewTemplateCommand::class,
                \Ihabrouk\Messenger\Commands\ManageTemplatesCommand::class,
                \Ihabrouk\Messenger\Commands\TestProviderCommand::class,
                \Ihabrouk\Messenger\Commands\ListProvidersCommand::class,
                \Ihabrouk\Messenger\Commands\ProcessWebhookCommand::class,
                \Ihabrouk\Messenger\Commands\CleanupLogsCommand::class,
            ]);
        }
    }

    /**
     * Register webhook routes
     */
    protected function registerRoutes(): void
    {
        if (config('messenger.webhooks.enabled', true)) {
            Route::prefix('messenger/webhook')
                ->middleware(['api'])
                ->group(function () {
                    Route::post('smsmisr', [\Ihabrouk\Messenger\Http\Controllers\SmsMisrWebhookController::class, 'handle'])
                        ->name('messenger.webhook.smsmisr');

                    Route::post('twilio', [\Ihabrouk\Messenger\Http\Controllers\TwilioWebhookController::class, 'handle'])
                        ->name('messenger.webhook.twilio');
                });
        }
    }

    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        $events = $this->app['events'];

        // Message events
        $events->listen(
            \Ihabrouk\Messenger\Events\MessageSent::class,
            \Ihabrouk\Messenger\Listeners\LogMessageDelivery::class
        );

        $events->listen(
            \Ihabrouk\Messenger\Events\MessageFailed::class,
            \Ihabrouk\Messenger\Listeners\LogMessageFailure::class
        );

        $events->listen(
            \Ihabrouk\Messenger\Events\MessageDelivered::class,
            \Ihabrouk\Messenger\Listeners\UpdateDeliveryStatus::class
        );

        // Bulk message events
        $events->listen(
            \Ihabrouk\Messenger\Events\BulkMessageStarted::class,
            \Ihabrouk\Messenger\Listeners\TrackBulkProgress::class
        );

        $events->listen(
            \Ihabrouk\Messenger\Events\BulkMessageCompleted::class,
            \Ihabrouk\Messenger\Listeners\NotifyBulkCompletion::class
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            MessengerServiceInterface::class,
            TemplateServiceInterface::class,
            MessageProviderFactory::class,
            \Ihabrouk\Messenger\Contracts\ProviderRegistryInterface::class,
            \Ihabrouk\Messenger\Services\ProviderService::class,
        ];
    }
}
EOF

# Create Facades
mkdir -p src/Facades
cat > src/Facades/Messenger.php << 'EOF'
<?php

namespace Ihabrouk\Messenger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ihabrouk\Messenger\Data\MessageResponse send(array $data)
 * @method static \Ihabrouk\Messenger\Data\MessageResponse sendFromTemplate(string $templateName, array $data)
 * @method static array bulkSend(\Ihabrouk\Messenger\Models\Batch $batch, array $recipients)
 * @method static bool scheduleMessage(array $data, \Carbon\Carbon $scheduledAt)
 * @method static bool cancelMessage(int $messageId)
 * @method static array getProviderHealth()
 *
 * @see \Ihabrouk\Messenger\Services\MessengerService
 */
class Messenger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ihabrouk\Messenger\Contracts\MessengerServiceInterface::class;
    }
}
EOF

echo "📄 Creating additional package files..."

# Create config directory and copy config
mkdir -p config
cp ../../../config/messenger.php config/

# Create resources directory structure
mkdir -p resources/{views/components/messenger,lang/en}

# Create basic view files
cat > resources/views/components/messenger/channel-selector.blade.php << 'EOF'
<div class="messenger-channel-selector">
    <!-- Channel selector component will be here -->
    <!-- This is a placeholder for the actual component -->
</div>
EOF

# Create language file
cat > resources/lang/en/messenger.php << 'EOF'
<?php

return [
    'messages' => [
        'sent' => 'Message sent successfully',
        'failed' => 'Failed to send message',
        'queued' => 'Message queued for delivery',
        'scheduled' => 'Message scheduled successfully',
        'cancelled' => 'Message cancelled',
    ],
    'providers' => [
        'smsmisr' => 'SMS Misr',
        'twilio' => 'Twilio',
        'mocktest' => 'Mock Test Provider',
    ],
    'channels' => [
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],
    'templates' => [
        'created' => 'Template created successfully',
        'updated' => 'Template updated successfully',
        'deleted' => 'Template deleted successfully',
        'not_found' => 'Template not found',
    ],
];
EOF

echo "📄 Creating tests directory..."
mkdir -p tests/{Feature,Unit}

cat > tests/TestCase.php << 'EOF'
<?php

namespace Ihabrouk\Messenger\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Ihabrouk\Messenger\MessengerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MessengerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
EOF

cat > tests/Feature/MessengerServiceTest.php << 'EOF'
<?php

use Ihabrouk\Messenger\Services\MessengerService;
use Ihabrouk\Messenger\Tests\TestCase;

class MessengerServiceTest extends TestCase
{
    public function test_messenger_service_can_be_resolved(): void
    {
        $service = $this->app->make(MessengerService::class);
        
        $this->assertInstanceOf(MessengerService::class, $service);
    }
}
EOF

# Create LICENSE file
cat > LICENSE.md << 'EOF'
MIT License

Copyright (c) 2025 Ihab Brouk

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
EOF

# Create CHANGELOG.md
cat > CHANGELOG.md << 'EOF'
# Changelog

All notable changes to `messenger` will be documented in this file.

## 1.0.0 - 2025-07-15

### Added
- Initial release
- Multi-provider messaging support (SMS Misr, Twilio)
- FilamentPHP integration
- Template system with variable substitution
- Bulk messaging capabilities
- Queue integration with priority handling
- Analytics and monitoring
- Circuit breaker pattern for reliability
- GDPR compliance and consent management
- Comprehensive test suite
- Documentation and examples

### Features
- SMS and WhatsApp messaging
- Real-time delivery tracking
- Cost estimation and monitoring
- Automated retry logic
- Webhook handling
- Multi-language support
- Rate limiting and security features
EOF

echo "✅ Package conversion completed!"
echo ""
echo "📦 Your package is ready at: $MESSENGER_DIR"
echo ""
echo "🚀 Next steps:"
echo "1. Review the generated files"
echo "2. Test the package locally"
echo "3. Create a GitHub repository"
echo "4. Set up Packagist"
echo "5. Tag your first release"
echo ""
echo "📋 Installation instructions for end users:"
echo "composer require ihabrouk/messenger"
echo "php artisan vendor:publish --tag=messenger-config"
echo "php artisan vendor:publish --tag=messenger-migrations"
echo "php artisan migrate"
