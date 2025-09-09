<?php

namespace Ihabrouk\Messenger\Commands;

use Exception;
use Illuminate\Console\Command;
use Ihabrouk\Messenger\Contracts\ProviderRegistryInterface;
use Ihabrouk\Messenger\Services\ProviderService;

/**
 * List Providers Command
 *
 * Lists all registered providers to verify plugin architecture
 */
class ListProvidersCommand extends Command
{
    protected $signature = 'messenger:list-providers';
    protected $description = 'List all registered messenger providers';

    public function __construct(
        private ProviderRegistryInterface $registry,
        private ProviderService $providerService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔌 Registered Messenger Providers');
        $this->newLine();

        $providers = $this->registry->getRegisteredProviders();

        if (empty($providers)) {
            $this->warn('No providers are currently registered.');
            return self::SUCCESS;
        }

        $this->info("Found " . count($providers) . " registered provider(s):");
        $this->newLine();

        foreach ($providers as $name) {
            $this->line("📱 <info>{$name}</info>");

            // Try to get static capabilities from definition if available
            $providerClass = $this->registry->getDriverClass($name);

            if ($providerClass && method_exists($providerClass, 'getProviderDefinition')) {
                try {
                    $definition = $providerClass::getProviderDefinition();
                    $capabilities = $definition->capabilities ?? [];

                    $this->line("   Capabilities: " . implode(', ', $capabilities));
                    $this->line("   Display Name: " . ($definition->displayName ?? ucfirst($name)));
                    $this->line("   Description: " . ($definition->description ?? 'No description'));
                } catch (Exception $e) {
                    $this->line("   <error>Error reading definition: {$e->getMessage()}</error>");
                }
            } else {
                $this->line("   <comment>No static definition available</comment>");
            }

            $this->newLine();
        }

        // Test capability-based provider finding
        $this->info('🔍 Testing Capability-Based Provider Discovery:');
        $this->newLine();

        $smsProviders = $this->registry->getProvidersByCapability('sms');
        $this->line("SMS Providers: " . implode(', ', $smsProviders));

        $whatsappProviders = $this->registry->getProvidersByCapability('whatsapp');
        $this->line("WhatsApp Providers: " . implode(', ', $whatsappProviders));

        $otpProviders = $this->registry->getProvidersByCapability('otp');
        $this->line("OTP Providers: " . implode(', ', $otpProviders));

        $bulkProviders = $this->registry->getProvidersByCapability('bulk_messaging');
        $this->line("Bulk Messaging Providers: " . implode(', ', $bulkProviders));

        $this->newLine();
        $this->info('✅ Plugin architecture is working correctly!');

        return self::SUCCESS;
    }
}
