<?php

namespace Ihabrouk\Messenger\Testing;

use Ihabrouk\Messenger\Contracts\MessageProviderInterface;
use Ihabrouk\Messenger\Data\MessageResponse;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Enums\MessageStatus;

class MockMessageProvider implements MessageProviderInterface
{
    private array $config;
    private bool $shouldFail;
    private array $customResponses;

    public function __construct(array $config = [], bool $shouldFail = false)
    {
        $this->config = $config;
        $this->shouldFail = $shouldFail;
        $this->customResponses = [];
    }

    public function send(SendMessageData $messageData): MessageResponse
    {
        if ($this->shouldFail) {
            throw new \Exception('Mock provider failure');
        }

        return new MessageResponse(
            success: true,
            status: MessageStatus::SENT,
            providerId: 'mock',
            providerMessageId: 'mock_' . uniqid(),
            cost: 0.10,
            metadata: $this->getCustomResponse('send') ?? ['status' => 'sent']
        );
    }

    public function sendBulk(array $messages): array
    {
        if ($this->shouldFail) {
            throw new \Exception('Mock provider bulk failure');
        }

        return collect($messages)->map(function ($messageData) {
            return new MessageResponse(
                success: true,
                status: MessageStatus::SENT,
                providerId: 'mock',
                providerMessageId: 'mock_bulk_' . uniqid(),
                cost: 0.10,
                metadata: $this->getCustomResponse('sendBulk') ?? ['status' => 'sent', 'message' => $messageData]
            );
        })->toArray();
    }

    public function getBalance(): float
    {
        return $this->customResponses['balance'] ?? 100.50;
    }

    public function isHealthy(): bool
    {
        return !$this->shouldFail;
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getProviderName(): string
    {
        return 'mock';
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        return !$this->shouldFail;
    }

    public function processWebhook(array $payload): array
    {
        return $this->getCustomResponse('processWebhook') ?? ['status' => 'processed', 'payload' => $payload];
    }

    public function getName(): string
    {
        return 'mock';
    }

    public function getSupportedTypes(): array
    {
        return ['sms', 'whatsapp', 'otp'];
    }

    public function getMaxRecipients(): int
    {
        return 1000;
    }

    /**
     * Get the capabilities supported by this provider.
     */
    public function getCapabilities(): array
    {
        return [
            'send',
            'sendBulk',
            'getBalance',
            'verifyWebhook',
            'processWebhook',
            'sms',
            'whatsapp',
            'otp',
        ];
    }

    /**
     * Check if a capability is supported by this provider.
     */
    public function supportsCapability(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * Set custom response for specific method
     */
    public function setCustomResponse(string $method, mixed $response): self
    {
        $this->customResponses[$method] = $response;
        return $this;
    }

    /**
     * Get custom response for method
     */
    private function getCustomResponse(string $method): mixed
    {
        return $this->customResponses[$method] ?? null;
    }

    /**
     * Set whether this provider should fail
     */
    public function setShouldFail(bool $shouldFail): self
    {
        $this->shouldFail = $shouldFail;
        return $this;
    }

    /**
     * Get failure state
     */
    public function getShouldFail(): bool
    {
        return $this->shouldFail;
    }
}
