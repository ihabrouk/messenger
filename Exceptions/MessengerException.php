<?php

namespace Ihabrouk\Messenger\Exceptions;

use Exception;

class MessengerException extends Exception
{
    protected string $errorCode;
    protected array $context;
    protected ?string $provider;
    protected ?string $errorType;

    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $context = [],
        ?string $provider = null,
        ?string $errorType = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->provider = $provider;
        $this->errorType = $errorType;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->getErrorCode(),
            'context' => $this->getContext(),
            'provider' => $this->getProvider(),
            'error_type' => $this->getErrorType(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    public function isTemporaryFailure(): bool
    {
        return $this->errorType === 'temporary';
    }

    public function isAuthenticationError(): bool
    {
        return $this->errorType === 'authentication';
    }

    public function isInsufficientCredit(): bool
    {
        return $this->errorType === 'insufficient_credit';
    }

    public function isInvalidRecipient(): bool
    {
        return $this->errorType === 'invalid_recipient';
    }

    public function isRateLimitExceeded(): bool
    {
        return $this->errorType === 'rate_limit';
    }

    public function isConfigurationError(): bool
    {
        return $this->errorType === 'configuration';
    }
}
