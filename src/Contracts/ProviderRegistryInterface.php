<?php

namespace Ihabrouk\Messenger\Contracts;

interface ProviderRegistryInterface
{
    /**
     * Register a provider
     */
    public function register(string $name, string $driverClass, array $capabilities = []): void;

    /**
     * Get all registered providers
     */
    public function getRegisteredProviders(): array;

    /**
     * Check if a provider is registered
     */
    public function isRegistered(string $name): bool;

    /**
     * Get provider driver class
     */
    public function getDriverClass(string $name): ?string;

    /**
     * Get provider capabilities
     */
    public function getCapabilities(string $name): array;

    /**
     * Find providers by capability
     */
    public function getProvidersByCapability(string $capability): array;
}
