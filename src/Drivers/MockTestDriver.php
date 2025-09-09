<?php

namespace Ihabrouk\Messenger\Drivers;

use Ihabrouk\Messenger\Data\MessageResponse;
use Ihabrouk\Messenger\Contracts\MessageProviderInterface;
use Ihabrouk\Messenger\Contracts\ProviderDefinitionInterface;
use Ihabrouk\Messenger\Data\ProviderDefinition;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Enums\MessageCapability;
use Ihabrouk\Messenger\Enums\MessageType;
use Ihabrouk\Messenger\Drivers\AbstractProvider;

/**
 * Mock Test Provider
 *
 * A test provider to verify the plugin architecture works without core changes
 */
class MockTestDriver extends AbstractProvider implements MessageProviderInterface, ProviderDefinitionInterface
{
    /**
     * Get provider definition for plugin architecture
     */
    public static function getProviderDefinition(): ProviderDefinition
    {
        return new ProviderDefinition(
            name: 'mocktest',
            displayName: 'Mock Test Provider',
            description: 'A test provider for verifying plugin architecture',
            capabilities: [
                'sms',
                'bulk_messaging',
            ],
            requiredConfig: ['api_key'],
            optionalConfig: ['debug_mode']
        );
    }

    /**
     * Send a single message
     */
    public function send(SendMessageData $data): MessageResponse
    {
        // Mock successful response
        return MessageResponse::success(
            providerId: 'mocktest',
            providerMessageId: 'mock_' . uniqid(),
            cost: 0.10,
            provider: 'mocktest',
            metadata: [
                'mock' => true,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Send bulk messages
     */
    public function sendBulk(array $messages): array
    {
        $results = [];
        foreach ($messages as $message) {
            $results[] = $this->send($message);
        }

        return $results;
    }

    /**
     * Get account balance
     */
    public function getBalance(): float
    {
        return 1000.00;
    }

    /**
     * Get supported message types
     */
    public function getSupportedTypes(): array
    {
        return [
            MessageType::SMS,
        ];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        // Mock webhook verification always passes
        return true;
    }

    /**
     * Process webhook payload
     */
    public function processWebhook(array $payload): array
    {
        return [
            'type' => 'delivery_status',
            'message_id' => $payload['message_id'] ?? 'mock_id',
            'status' => 'delivered',
            'mock' => true,
        ];
    }

    /**
     * Get provider name
     */
    public function getName(): string
    {
        return 'mocktest';
    }

    /**
     * Get maximum recipients per request
     */
    public function getMaxRecipients(): int
    {
        return 1000;
    }

    /**
     * Check if provider is healthy
     */
    public function isHealthy(): bool
    {
        return true;
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'api_key' => 'mock_test_key',
            'debug_mode' => true,
        ];
    }

    /**
     * Validate configuration
     */
    protected function validateConfig(): void
    {
        $this->ensureConfigExists(['api_key']);
    }

    /**
     * Get required configuration keys
     */
    protected function getRequiredConfigKeys(): array
    {
        return ['api_key'];
    }

    /**
     * Extract response code from API response
     */
    protected function extractResponseCode(array $response): string
    {
        return $response['code'] ?? '200';
    }

    /**
     * Extract provider ID from response
     */
    protected function extractProviderId(array $response): string
    {
        return 'mocktest';
    }

    /**
     * Extract message ID from response
     */
    protected function extractMessageId(array $response): ?string
    {
        return $response['message_id'] ?? 'mock_' . uniqid();
    }

    /**
     * Extract cost from response
     */
    protected function extractCost(array $response): ?float
    {
        return $response['cost'] ?? 0.10;
    }

    /**
     * Build payload for API request
     */
    protected function buildPayload(SendMessageData $data): array
    {
        return [
            'to' => $data->to,
            'message' => $data->body,
            'mock' => true,
        ];
    }

    /**
     * Build headers for API request
     */
    protected function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config['api_key'],
        ];
    }

    /**
     * Get API URL for request
     */
    protected function getApiUrl(SendMessageData $data): string
    {
        return 'https://mock-api.test/send';
    }

    /**
     * Perform health check
     */
    public function healthCheck(): array
    {
        return [
            'status' => 'healthy',
            'provider' => 'mocktest',
            'mock' => true,
            'timestamp' => now()->toISOString(),
        ];
    }
}
