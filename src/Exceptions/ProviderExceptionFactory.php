<?php

namespace App\Messenger\Exceptions;

class ProviderExceptionFactory
{
    /**
     * Create exception from provider response
     */
    public static function fromProviderResponse(
        string $provider,
        string $responseCode,
        array $context = []
    ): MessengerException {
        $mapping = self::getErrorMapping($provider, $responseCode);

        return new MessengerException(
            message: $mapping['message'],
            errorCode: $responseCode,
            context: $context,
            provider: $provider,
            errorType: $mapping['type']
        );
    }

    /**
     * Create success exception (for providers that return success codes)
     */
    public static function success(
        string $provider,
        string $responseCode,
        array $context = []
    ): MessengerException {
        $mapping = self::getErrorMapping($provider, $responseCode);

        return new MessengerException(
            message: $mapping['message'],
            errorCode: $responseCode,
            context: $context,
            provider: $provider,
            errorType: 'success'
        );
    }

    /**
     * Get error mapping for provider and response code
     */
    protected static function getErrorMapping(string $provider, string $responseCode): array
    {
        $mappings = config("messenger.providers.{$provider}.error_mappings", []);

        if (isset($mappings[$responseCode])) {
            return $mappings[$responseCode];
        }

        // Fallback to default mapping
        return [
            'message' => "Unknown {$provider} error code: {$responseCode}",
            'type' => 'unknown',
        ];
    }

    /**
     * Create exception for HTTP errors
     */
    public static function httpError(
        string $provider,
        int $statusCode,
        string $message,
        array $context = []
    ): MessengerException {
        $type = match (true) {
            $statusCode >= 500 => 'temporary',
            $statusCode === 429 => 'rate_limit',
            $statusCode === 401 || $statusCode === 403 => 'authentication',
            default => 'client_error',
        };

        return new MessengerException(
            message: "HTTP {$statusCode}: {$message}",
            errorCode: "HTTP_{$statusCode}",
            context: array_merge($context, ['status_code' => $statusCode]),
            provider: $provider,
            errorType: $type
        );
    }

    /**
     * Create exception for connection errors
     */
    public static function connectionError(
        string $provider,
        string $message,
        array $context = []
    ): MessengerException {
        return new MessengerException(
            message: "Connection error: {$message}",
            errorCode: 'CONNECTION_ERROR',
            context: $context,
            provider: $provider,
            errorType: 'temporary'
        );
    }

    /**
     * Create exception for configuration errors
     */
    public static function configurationError(
        string $provider,
        string $message,
        array $context = []
    ): MessengerException {
        return new MessengerException(
            message: "Configuration error: {$message}",
            errorCode: 'CONFIGURATION_ERROR',
            context: $context,
            provider: $provider,
            errorType: 'configuration'
        );
    }

    /**
     * Create exception for validation errors
     */
    public static function validationError(
        string $provider,
        string $field,
        string $message,
        array $context = []
    ): MessengerException {
        return new MessengerException(
            message: "Validation error for {$field}: {$message}",
            errorCode: 'VALIDATION_ERROR',
            context: array_merge($context, ['field' => $field]),
            provider: $provider,
            errorType: 'validation'
        );
    }
}
