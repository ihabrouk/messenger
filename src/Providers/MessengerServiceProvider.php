<?php

namespace Ihabrouk\Messenger\Providers;

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
            __DIR__ . '/../../config/messenger.php',
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
                __DIR__ . '/../../config/messenger.php' => config_path('messenger.php'),
            ], 'messenger-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'messenger-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../../../Resources/views/components/messenger/' => resource_path('views/components/messenger'),
            ], 'messenger-views');

            // Publish language files
            $this->publishes([
                __DIR__ . '/../../../Resources/lang/en/messenger.php' => lang_path('en/messenger.php'),
            ], 'messenger-lang');
        }

        // Load migrations - commented out as migrations are now in database/migrations directory
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../../Resources/views', 'messenger');

        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../../../Resources/lang', 'messenger');

        // Auto-discover and register providers
        $registry = $this->app->make(\Ihabrouk\Messenger\Contracts\ProviderRegistryInterface::class);

        // For now, we'll manually register since we don't have service container tags set up
        // In a full package implementation, this would use tagged services

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
