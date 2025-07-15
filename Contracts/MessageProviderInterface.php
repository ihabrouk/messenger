<?php

namespace Ihabrouk\Messenger\Contracts;

use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Data\MessageResponse;

interface MessageProviderInterface
{
    /**
     * Send a single message
     */
    public function send(SendMessageData $data): MessageResponse;

    /**
     * Send bulk messages
     */
    public function sendBulk(array $messages): array;

    /**
     * Check account balance
     */
    public function getBalance(): float;

    /**
     * Verify webhook signature
     */
    public function verifyWebhook(string $payload, string $signature): bool;

    /**
     * Process webhook payload
     */
    public function processWebhook(array $payload): array;

    /**
     * Get provider name
     */
    public function getName(): string;

    /**
     * Get supported message types
     */
    public function getSupportedTypes(): array;

    /**
     * Get provider capabilities (e.g., 'bulk_messaging', 'scheduling', 'templates')
     */
    public function getCapabilities(): array;

    /**
     * Check if provider supports a specific capability
     */
    public function supportsCapability(string $capability): bool;

    /**
     * Get maximum recipients per request
     */
    public function getMaxRecipients(): int;

    /**
     * Check if provider is healthy
     */
    public function isHealthy(): bool;
}
