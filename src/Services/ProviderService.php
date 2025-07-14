<?php

namespace App\Messenger\Services;

use App\Messenger\Contracts\ProviderRegistryInterface;
use App\Messenger\Enums\MessageCapability;

/**
 * Provider Service
 *
 * Provides dynamic provider information replacing hardcoded enums
 */
class ProviderService
{
    public function __construct(
        private ProviderRegistryInterface $registry
    ) {}

    /**
     * Get all available provider names
     */
    public function getProviderNames(): array
    {
        return $this->registry->getRegisteredProviders();
    }

    /**
     * Get providers by capability
     */
    public function getProvidersByCapability(MessageCapability $capability): array
    {
        return $this->registry->getProvidersByCapability($capability);
    }

    /**
     * Check if a provider is registered
     */
    public function isProviderRegistered(string $name): bool
    {
        return in_array($name, $this->getProviderNames());
    }

    /**
     * Get provider for select options
     */
    public function getProviderOptions(): array
    {
        $options = [];
        foreach ($this->getProviderNames() as $name) {
            $options[$name] = ucfirst($name);
        }
        return $options;
    }

    /**
     * Get providers with capabilities for admin interface
     */
    public function getProvidersWithCapabilities(): array
    {
        $providers = [];
        foreach ($this->getProviderNames() as $name) {
            try {
                $instance = $this->registry->make($name);
                $providers[$name] = [
                    'name' => $name,
                    'display_name' => ucfirst($name),
                    'capabilities' => $instance->getCapabilities(),
                ];
            } catch (\Exception $e) {
                // Skip providers that can't be instantiated
                continue;
            }
        }
        return $providers;
    }
}
