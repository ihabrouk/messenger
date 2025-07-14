<?php

namespace App\Messenger\Services;

use App\Messenger\Contracts\ProviderRegistryInterface;
use App\Messenger\Contracts\MessageProviderInterface;
use App\Messenger\Exceptions\MessengerException;
use Illuminate\Container\Container;

class ProviderRegistry implements ProviderRegistryInterface
{
    private array $providers = [];
    private array $capabilities = [];

    public function __construct(
        private Container $container
    ) {}

    public function register(string $name, string $className, array $capabilities = []): void
    {
        $this->providers[$name] = $className;
        $this->capabilities[$name] = $capabilities;
    }

    public function isRegistered(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function getRegisteredProviders(): array
    {
        return array_keys($this->providers);
    }

    public function getProviderClass(string $name): ?string
    {
        return $this->providers[$name] ?? null;
    }

    public function getDriverClass(string $name): ?string
    {
        return $this->providers[$name] ?? null;
    }

    public function getProviderCapabilities(string $name): array
    {
        return $this->capabilities[$name] ?? [];
    }

    public function getCapabilities(string $name): array
    {
        return $this->capabilities[$name] ?? [];
    }

    public function getProvidersByCapability(string $capability): array
    {
        $providers = [];

        foreach ($this->capabilities as $providerName => $caps) {
            if (in_array($capability, $caps)) {
                $providers[] = $providerName;
            }
        }

        return $providers;
    }

    /**
     * Make an instance of a registered provider
     */
    public function make(string $name): MessageProviderInterface
    {
        if (!$this->isRegistered($name)) {
            throw new \InvalidArgumentException("Provider '{$name}' is not registered");
        }

        $className = $this->providers[$name];

        // Use the container to resolve the provider with dependencies
        return $this->container->make($className);
    }

    /**
     * Auto-discover providers from tagged services
     */
    public function discoverProviders(array $taggedProviders): void
    {
        foreach ($taggedProviders as $providerClass) {
            if (method_exists($providerClass, 'getProviderDefinition')) {
                $definition = $providerClass::getProviderDefinition();
                $this->register(
                    $definition['name'],
                    $providerClass,
                    $definition['capabilities'] ?? []
                );
            }
        }
    }
}
