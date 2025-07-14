<?php

namespace App\Messenger\Services;

use App\Messenger\Contracts\MessageProviderInterface;
use App\Messenger\Contracts\ProviderRegistryInterface;
use App\Messenger\Exceptions\ProviderExceptionFactory;
use Illuminate\Container\Container;

class MessageProviderFactory
{
    protected Container $container;
    protected ProviderRegistryInterface $registry;
    protected array $providers;
    protected array $instances = [];

    public function __construct(Container $container, ProviderRegistryInterface $registry, array $providers = [])
    {
        $this->container = $container;
        $this->registry = $registry;
        $this->providers = $providers;
    }

    /**
     * Create or get provider instance
     */
    public function make(string $providerName): MessageProviderInterface
    {
        if (isset($this->instances[$providerName])) {
            return $this->instances[$providerName];
        }

        if (!$this->registry->isRegistered($providerName)) {
            throw ProviderExceptionFactory::configurationError(
                $providerName,
                "Provider '{$providerName}' is not registered"
            );
        }

        if (!isset($this->providers[$providerName])) {
            throw ProviderExceptionFactory::configurationError(
                $providerName,
                "Provider '{$providerName}' is not configured"
            );
        }

        $config = $this->providers[$providerName];
        $driverClass = $this->registry->getDriverClass($providerName);

        if (!class_exists($driverClass)) {
            throw ProviderExceptionFactory::configurationError(
                $providerName,
                "Driver class '{$driverClass}' does not exist"
            );
        }

        try {
            $instance = new $driverClass($config, $providerName);

            if (!$instance instanceof MessageProviderInterface) {
                throw ProviderExceptionFactory::configurationError(
                    $providerName,
                    "Driver '{$driverClass}' must implement MessageProviderInterface"
                );
            }

            $this->instances[$providerName] = $instance;
            return $instance;
        } catch (\Exception $e) {
            throw ProviderExceptionFactory::configurationError(
                $providerName,
                "Failed to create provider instance: {$e->getMessage()}",
                ['exception' => $e]
            );
        }
    }

    /**
     * Get provider for specific channel
     */
    public function makeForChannel(string $channel, ?string $providerName = null): MessageProviderInterface
    {
        if ($providerName) {
            return $this->make($providerName);
        }

        $channelConfig = config("messenger.channels.{$channel}");

        if (!$channelConfig) {
            throw ProviderExceptionFactory::configurationError(
                'unknown',
                "Channel '{$channel}' is not configured"
            );
        }

        $defaultProvider = $channelConfig['default'] ?? null;

        if (!$defaultProvider) {
            throw ProviderExceptionFactory::configurationError(
                'unknown',
                "No default provider configured for channel '{$channel}'"
            );
        }

        return $this->make($defaultProvider);
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get providers for specific channel
     */
    public function getProvidersForChannel(string $channel): array
    {
        $channelConfig = config("messenger.channels.{$channel}");
        return $channelConfig['providers'] ?? [];
    }

    /**
     * Check if provider supports specific message type
     */
    public function supportsMessageType(string $providerName, string $messageType): bool
    {
        try {
            $provider = $this->make($providerName);
            return in_array($messageType, $provider->getSupportedTypes());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get health status of all providers
     */
    public function getHealthStatus(): array
    {
        $status = [];

        foreach ($this->getAvailableProviders() as $providerName) {
            try {
                $provider = $this->make($providerName);
                $status[$providerName] = [
                    'healthy' => $provider->isHealthy(),
                    'error' => null,
                ];
            } catch (\Exception $e) {
                $status[$providerName] = [
                    'healthy' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $status;
    }

    /**
     * Get driver class name from driver identifier
     */
    protected function getDriverClass(string $driver): string
    {
        $driverMap = [
            'smsmisr' => \App\Messenger\Drivers\SmsMisrDriver::class,
            'twilio' => \App\Messenger\Drivers\TwilioDriver::class,
        ];

        return $driverMap[$driver] ?? "\\App\\Messenger\\Drivers\\" . ucfirst($driver) . "Driver";
    }
}
