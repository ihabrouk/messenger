<?php

namespace App\Messenger\Exceptions;

class SmsMisrException extends MessengerException
{
    public static function fromResponseCode(string $code, array $context = []): self
    {
        return match ($code) {
            '1901' => new self('Message submitted successfully', $code, $context),
            '1902' => new self('Invalid request', $code, $context),
            '1903' => new self('Invalid username or password', $code, $context),
            '1904' => new self('Invalid sender field', $code, $context),
            '1905' => new self('Invalid mobile field', $code, $context),
            '1906' => new self('Insufficient credit', $code, $context),
            '1907' => new self('Server under updating', $code, $context),
            '1908' => new self('Invalid date & time format in DelayUntil parameter', $code, $context),
            '1909' => new self('Invalid message', $code, $context),
            '1910' => new self('Invalid language', $code, $context),
            '1911' => new self('Text is too long', $code, $context),
            '1912' => new self('Invalid environment', $code, $context),
            '4901' => new self('OTP message submitted successfully', $code, $context),
            '4903' => new self('Invalid username or password for OTP', $code, $context),
            '4904' => new self('Invalid sender field for OTP', $code, $context),
            '4905' => new self('Invalid mobile field for OTP', $code, $context),
            '4906' => new self('Insufficient credit for OTP', $code, $context),
            '4907' => new self('Server under updating for OTP', $code, $context),
            '4908' => new self('Invalid OTP', $code, $context),
            '4909' => new self('Invalid template token', $code, $context),
            '4912' => new self('Invalid environment for OTP', $code, $context),
            default => new self("Unknown SMS Misr error code: {$code}", $code, $context),
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this->getErrorCode(), ['1901', '4901']);
    }

    public function isTemporaryFailure(): bool
    {
        return in_array($this->getErrorCode(), ['1907', '4907']);
    }

    public function isAuthenticationError(): bool
    {
        return in_array($this->getErrorCode(), ['1903', '4903']);
    }

    public function isInsufficientCredit(): bool
    {
        return in_array($this->getErrorCode(), ['1906', '4906']);
    }
}
