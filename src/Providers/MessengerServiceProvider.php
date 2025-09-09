<?php

namespace Ihabrouk\Messenger\Providers;

use Ihabrouk\Messenger\Contracts\ProviderRegistryInterface;
use Ihabrouk\Messenger\Services\ProviderRegistry;
use Ihabrouk\Messenger\Services\ProviderService;
use Ihabrouk\Messenger\Services\TemplateManager;
use Ihabrouk\Messenger\Services\TemplateValidator;
use Ihabrouk\Messenger\Services\DeliveryTrackingService;
use Ihabrouk\Messenger\Services\CircuitBreakerService;
use Ihabrouk\Messenger\Services\BulkMessageService;
use Ihabrouk\Messenger\Services\MonitoringService;
use Ihabrouk\Messenger\Services\AutomationService;
use Ihabrouk\Messenger\Services\ConsentService;
use Ihabrouk\Messenger\Services\AnalyticsService;
use Ihabrouk\Messenger\Commands\MakeDriverCommand;
use Ihabrouk\Messenger\Commands\MakeTemplateCommand;
use Ihabrouk\Messenger\Commands\TestProviderCommand;
use Ihabrouk\Messenger\Commands\ListProvidersCommand;
use Ihabrouk\Messenger\Commands\ProcessWebhookCommand;
use Ihabrouk\Messenger\Commands\CleanupLogsCommand;
use Ihabrouk\Messenger\Commands\MessengerAutomationCommand;
use Ihabrouk\Messenger\Commands\MessengerStatusCommand;
use Ihabrouk\Messenger\Commands\DiagnoseInstallationCommand;
use Ihabrouk\Messenger\Drivers\SmsMisrDriver;
use Ihabrouk\Messenger\Drivers\TwilioDriver;
use Ihabrouk\Messenger\Drivers\MockTestDriver;
use Ihabrouk\Messenger\Commands\SendMessageCommand;
use Ihabrouk\Messenger\Commands\ValidateTemplateCommand;
use Ihabrouk\Messenger\Commands\PreviewTemplateCommand;
use Ihabrouk\Messenger\Commands\ManageTemplatesCommand;
use Ihabrouk\Messenger\Http\Controllers\SmsMisrWebhookController;
use Ihabrouk\Messenger\Http\Controllers\TwilioWebhookController;
use Ihabrouk\Messenger\Events\MessageSent;
use Ihabrouk\Messenger\Listeners\LogMessageSent;
use Ihabrouk\Messenger\Events\MessageFailed;
use Ihabrouk\Messenger\Listeners\LogMessageFailure;
use Ihabrouk\Messenger\Events\MessageDelivered;
use Ihabrouk\Messenger\Listeners\LogMessageDelivered;
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
        $this->app->singleton(ProviderRegistryInterface::class, ProviderRegistry::class);

        // Register core services
        $this->app->singleton(MessengerServiceInterface::class, MessengerService::class);
        $this->app->singleton(TemplateServiceInterface::class, TemplateService::class);
        $this->app->singleton(ProviderService::class);

        // Register template services
        $this->app->singleton(TemplateManager::class);
        $this->app->singleton(TemplateValidator::class);

        // Register new Phase 6 services
        $this->app->singleton(DeliveryTrackingService::class);
        $this->app->singleton(CircuitBreakerService::class);
        $this->app->singleton(BulkMessageService::class);
        $this->app->singleton(MonitoringService::class);
        $this->app->singleton(AutomationService::class);

        // Register new Phase 7 services
        $this->app->singleton(ConsentService::class);
        $this->app->singleton(AnalyticsService::class);

        // Register factory with registry
        $this->app->singleton(MessageProviderFactory::class, function ($app) {
            return new MessageProviderFactory(
                $app,
                $app->make(ProviderRegistryInterface::class),
                config('messenger.providers', [])
            );
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeDriverCommand::class,
                MakeTemplateCommand::class,
                TestProviderCommand::class,
                ListProvidersCommand::class,
                ProcessWebhookCommand::class,
                CleanupLogsCommand::class,
                MessengerAutomationCommand::class,
                MessengerStatusCommand::class,
                DiagnoseInstallationCommand::class,
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
                __DIR__ . '/../Database/migrations/' => database_path('migrations'),
            ], 'messenger-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../Resources/views/components/messenger/' => resource_path('views/components/messenger'),
            ], 'messenger-views');

            // Publish language files
            $this->publishes([
                __DIR__ . '/../Resources/lang/en/messenger.php' => lang_path('en/messenger.php'),
            ], 'messenger-lang');
        }

        // Load migrations - commented out as migrations are now in Database/migrations directory
        // $this->loadMigrationsFrom(__DIR__ . '/../Database/migrations');

        // Load views (only if directory exists)
        $viewsPath = __DIR__ . '/../Resources/views';
        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'messenger');
        }

        // Load translations (only if directory exists)
        $langPath = __DIR__ . '/../Resources/lang';
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'messenger');
        }

        // Auto-discover and register providers
        $registry = $this->app->make(ProviderRegistryInterface::class);

        // For now, we'll manually register since we don't have service container tags set up
        // In a full package implementation, this would use tagged services

        // Manually register built-in providers with capabilities
        $registry->register('smsmisr', SmsMisrDriver::class, ['sms', 'otp', 'bulk_messaging']);
        $registry->register('twilio', TwilioDriver::class, ['sms', 'whatsapp', 'bulk_messaging']);

        // Register test provider to verify plugin architecture
        $registry->register('mocktest', MockTestDriver::class, ['sms', 'bulk_messaging']);

        // Register routes
        $this->registerRoutes();

        // Register event listeners
        $this->registerEventListeners();

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendMessageCommand::class,
                ValidateTemplateCommand::class,
                PreviewTemplateCommand::class,
                ManageTemplatesCommand::class,
                TestProviderCommand::class,
                ListProvidersCommand::class,
                ProcessWebhookCommand::class,
                CleanupLogsCommand::class,
                DiagnoseInstallationCommand::class,
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
                    Route::post('smsmisr', [SmsMisrWebhookController::class, 'handle'])
                        ->name('messenger.webhook.smsmisr');

                    Route::post('twilio', [TwilioWebhookController::class, 'handle'])
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
            MessageSent::class,
            LogMessageSent::class
        );

        $events->listen(
            MessageFailed::class,
            LogMessageFailure::class
        );

        $events->listen(
            MessageDelivered::class,
            LogMessageDelivered::class
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
            ProviderRegistryInterface::class,
            ProviderService::class,
        ];
    }
}
