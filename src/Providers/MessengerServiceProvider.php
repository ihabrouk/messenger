<?php

namespace App\Messenger\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Messenger\Contracts\MessengerServiceInterface;
use App\Messenger\Contracts\TemplateServiceInterface;
use App\Messenger\Services\MessengerService;
use App\Messenger\Services\TemplateService;
use App\Messenger\Services\MessageProviderFactory;

class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../../config/messenger.php',
            'messenger'
        );

        // Register provider registry
        $this->app->singleton(\App\Messenger\Contracts\ProviderRegistryInterface::class, \App\Messenger\Services\ProviderRegistry::class);

        // Register core services
        $this->app->singleton(MessengerServiceInterface::class, MessengerService::class);
        $this->app->singleton(TemplateServiceInterface::class, TemplateService::class);
        $this->app->singleton(\App\Messenger\Services\ProviderService::class);

        // Register template services
        $this->app->singleton(\App\Messenger\Services\TemplateManager::class);
        $this->app->singleton(\App\Messenger\Services\TemplateValidator::class);

        // Register new Phase 6 services
        $this->app->singleton(\App\Messenger\Services\DeliveryTrackingService::class);
        $this->app->singleton(\App\Messenger\Services\CircuitBreakerService::class);
        $this->app->singleton(\App\Messenger\Services\BulkMessageService::class);
        $this->app->singleton(\App\Messenger\Services\MonitoringService::class);
        $this->app->singleton(\App\Messenger\Services\AutomationService::class);

        // Register new Phase 7 services
        $this->app->singleton(\App\Messenger\Services\ConsentService::class);
        $this->app->singleton(\App\Messenger\Services\AnalyticsService::class);

        // Register factory with registry
        $this->app->singleton(MessageProviderFactory::class, function ($app) {
            return new MessageProviderFactory(
                $app,
                $app->make(\App\Messenger\Contracts\ProviderRegistryInterface::class),
                config('messenger.providers', [])
            );
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Messenger\Commands\MakeDriverCommand::class,
                \App\Messenger\Commands\MakeTemplateCommand::class,
                \App\Messenger\Commands\TestProviderCommand::class,
                \App\Messenger\Commands\ListProvidersCommand::class,
                \App\Messenger\Commands\ProcessWebhookCommand::class,
                \App\Messenger\Commands\CleanupLogsCommand::class,
                \App\Messenger\Commands\MessengerAutomationCommand::class,
                \App\Messenger\Commands\MessengerStatusCommand::class,
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
                __DIR__ . '/../../../config/messenger.php' => config_path('messenger.php'),
            ], 'messenger-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'messenger-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../../../resources/views/components/messenger/' => resource_path('views/components/messenger'),
            ], 'messenger-views');

            // Publish language files
            $this->publishes([
                __DIR__ . '/../../../lang/en/messenger.php' => lang_path('en/messenger.php'),
            ], 'messenger-lang');
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../../resources/views', 'messenger');

        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../../../lang', 'messenger');

        // Auto-discover and register providers
        $registry = $this->app->make(\App\Messenger\Contracts\ProviderRegistryInterface::class);

        // For now, we'll manually register since we don't have service container tags set up
        // In a full package implementation, this would use tagged services

        // Manually register built-in providers with capabilities
        $registry->register('smsmisr', \App\Messenger\Drivers\SmsMisrDriver::class, ['sms', 'otp', 'bulk_messaging']);
        $registry->register('twilio', \App\Messenger\Drivers\TwilioDriver::class, ['sms', 'whatsapp', 'bulk_messaging']);

        // Register test provider to verify plugin architecture
        $registry->register('mocktest', \App\Messenger\Drivers\MockTestDriver::class, ['sms', 'bulk_messaging']);

        // Register routes
        $this->registerRoutes();

        // Register event listeners
        $this->registerEventListeners();

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Messenger\Commands\SendMessageCommand::class,
                \App\Messenger\Commands\ValidateTemplateCommand::class,
                \App\Messenger\Commands\PreviewTemplateCommand::class,
                \App\Messenger\Commands\ManageTemplatesCommand::class,
                \App\Messenger\Commands\TestProviderCommand::class,
                \App\Messenger\Commands\ListProvidersCommand::class,
                \App\Messenger\Commands\ProcessWebhookCommand::class,
                \App\Messenger\Commands\CleanupLogsCommand::class,
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
                    Route::post('smsmisr', [\App\Messenger\Http\Controllers\SmsMisrWebhookController::class, 'handle'])
                        ->name('messenger.webhook.smsmisr');

                    Route::post('twilio', [\App\Messenger\Http\Controllers\TwilioWebhookController::class, 'handle'])
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
            \App\Messenger\Events\MessageSent::class,
            \App\Messenger\Listeners\LogMessageDelivery::class
        );

        $events->listen(
            \App\Messenger\Events\MessageFailed::class,
            \App\Messenger\Listeners\LogMessageFailure::class
        );

        $events->listen(
            \App\Messenger\Events\MessageDelivered::class,
            \App\Messenger\Listeners\UpdateDeliveryStatus::class
        );

        // Bulk message events
        $events->listen(
            \App\Messenger\Events\BulkMessageStarted::class,
            \App\Messenger\Listeners\TrackBulkProgress::class
        );

        $events->listen(
            \App\Messenger\Events\BulkMessageCompleted::class,
            \App\Messenger\Listeners\NotifyBulkCompletion::class
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
            \App\Messenger\Contracts\ProviderRegistryInterface::class,
            \App\Messenger\Services\ProviderService::class,
        ];
    }
}
