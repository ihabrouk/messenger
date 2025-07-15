<?php

namespace Ihabrouk\Messenger\Drivers;

use Ihabrouk\Messenger\Contracts\MessageProviderInterface;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Data\MessageResponse;
use Ihabrouk\Messenger\Exceptions\ProviderExceptionFactory;
use Ihabrouk\Messenger\Exceptions\MessengerException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;

abstract class AbstractProvider implements MessageProviderInterface
{
    protected Client $client;
    protected array $config;
    protected string $providerName;

    public function __construct(array $config, string $providerName)
    {
        $this->config = $config;
        $this->providerName = $providerName;
        $this->client = new Client([
            'timeout' => $config['timeout'] ?? 30,
            'verify' => true,
            'headers' => [
                'User-Agent' => 'Messenger-Package/1.0',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getName(): string
    {
        return $this->providerName;
    }

    public function getCapabilities(): array
    {
        return $this->config['capabilities'] ?? [];
    }

    public function supportsCapability(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities());
    }

    public function getMaxRecipients(): int
    {
        return $this->config['max_recipients'] ?? 1000;
    }

    /**
     * Handle HTTP requests with proper error handling
     */
    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $url, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ProviderExceptionFactory::validationError(
                    $this->providerName,
                    'response',
                    'Invalid JSON response from provider'
                );
            }

            return $data;
        } catch (ConnectException $e) {
            throw ProviderExceptionFactory::connectionError(
                $this->providerName,
                $e->getMessage(),
                ['url' => $url]
            );
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?? 0;
            throw ProviderExceptionFactory::httpError(
                $this->providerName,
                $statusCode,
                $e->getMessage(),
                ['url' => $url]
            );
        }
    }

    /**
     * Parse provider response and handle errors
     */
    protected function parseProviderResponse(array $response, array $context = []): MessageResponse
    {
        $responseCode = $this->extractResponseCode($response);

        if ($this->isSuccessResponse($responseCode)) {
            return MessageResponse::success(
                providerId: $this->extractProviderId($response),
                providerMessageId: $this->extractMessageId($response),
                cost: $this->extractCost($response),
                provider: $this->providerName,
                metadata: $response
            );
        }

        $exception = ProviderExceptionFactory::fromProviderResponse(
            $this->providerName,
            $responseCode,
            array_merge($context, $response)
        );

        return MessageResponse::failure(
            errorCode: $responseCode,
            errorMessage: $exception->getMessage(),
            provider: $this->providerName,
            metadata: $response
        );
    }

    /**
     * Validate configuration
     */
    protected function validateConfig(): void
    {
        $required = $this->getRequiredConfigKeys();

        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                throw ProviderExceptionFactory::configurationError(
                    $this->providerName,
                    "Missing required configuration: {$key}"
                );
            }
        }
    }

    /**
     * Log provider activity
     */
    protected function logActivity(string $action, array $context = []): void
    {
        Log::info("Messenger {$this->providerName}: {$action}", [
            'provider' => $this->providerName,
            'context' => $context,
        ]);
    }

    /**
     * Check if response code indicates success
     */
    protected function isSuccessResponse(string $responseCode): bool
    {
        $mapping = $this->config['error_mappings'][$responseCode] ?? null;
        return $mapping && $mapping['type'] === 'success';
    }

    /**
     * Get required configuration keys for this provider
     */
    abstract protected function getRequiredConfigKeys(): array;

    /**
     * Extract response code from provider response
     */
    abstract protected function extractResponseCode(array $response): string;

    /**
     * Extract provider ID from response
     */
    abstract protected function extractProviderId(array $response): string;

    /**
     * Extract message ID from response
     */
    abstract protected function extractMessageId(array $response): ?string;

    /**
     * Extract cost from response
     */
    abstract protected function extractCost(array $response): ?float;

    /**
     * Build request payload for the provider
     */
    abstract protected function buildPayload(SendMessageData $data): array;

    /**
     * Build request headers for the provider
     */
    abstract protected function buildHeaders(): array;

    /**
     * Get the API endpoint URL
     */
    abstract protected function getApiUrl(SendMessageData $data): string;
}
