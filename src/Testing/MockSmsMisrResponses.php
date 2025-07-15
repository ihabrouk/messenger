<?php

namespace Ihabrouk\Messenger\Testing;

class MockSmsMisrResponses
{
    /**
     * Mock successful SMS response
     */
    public static function successfulSms(): array
    {
        return [
            'code' => '1901',
            'SMSID' => 'SMS_' . uniqid(),
            'Cost' => '0.10',
        ];
    }

    /**
     * Mock successful OTP response
     */
    public static function successfulOtp(): array
    {
        return [
            'code' => '4901',
            'SMSID' => 'OTP_' . uniqid(),
            'Cost' => '0.12',
        ];
    }

    /**
     * Mock authentication error
     */
    public static function authenticationError(): array
    {
        return [
            'code' => '1903',
            'message' => 'Invalid username or password',
        ];
    }

    /**
     * Mock insufficient credit error
     */
    public static function insufficientCredit(): array
    {
        return [
            'code' => '1906',
            'message' => 'Insufficient credit',
        ];
    }

    /**
     * Mock invalid mobile number error
     */
    public static function invalidMobile(): array
    {
        return [
            'code' => '1905',
            'message' => 'Invalid mobile field',
        ];
    }

    /**
     * Mock server updating error (temporary failure)
     */
    public static function serverUpdating(): array
    {
        return [
            'code' => '1907',
            'message' => 'Server under updating',
        ];
    }

    /**
     * Mock invalid OTP error
     */
    public static function invalidOtp(): array
    {
        return [
            'code' => '4908',
            'message' => 'Invalid OTP',
        ];
    }

    /**
     * Mock invalid template error
     */
    public static function invalidTemplate(): array
    {
        return [
            'code' => '4909',
            'message' => 'Invalid template token',
        ];
    }

    /**
     * Mock balance response
     */
    public static function balanceResponse(float $balance = 100.50): array
    {
        return [
            'Balance' => $balance,
            'Currency' => 'EGP',
        ];
    }

    /**
     * Mock webhook delivery notification
     */
    public static function webhookDelivered(string $smsId = null): array
    {
        return [
            'SMSID' => $smsId ?? 'SMS_' . uniqid(),
            'Status' => 'delivered',
            'DeliveredAt' => now()->toISOString(),
            'Mobile' => '201234567890',
        ];
    }

    /**
     * Mock webhook failure notification
     */
    public static function webhookFailed(string $smsId = null): array
    {
        return [
            'SMSID' => $smsId ?? 'SMS_' . uniqid(),
            'Status' => 'failed',
            'FailedAt' => now()->toISOString(),
            'Mobile' => '201234567890',
            'ErrorCode' => 'INVALID_NUMBER',
        ];
    }

    /**
     * Get all mock responses for testing
     */
    public static function getAllMockResponses(): array
    {
        return [
            'successful_sms' => self::successfulSms(),
            'successful_otp' => self::successfulOtp(),
            'authentication_error' => self::authenticationError(),
            'insufficient_credit' => self::insufficientCredit(),
            'invalid_mobile' => self::invalidMobile(),
            'server_updating' => self::serverUpdating(),
            'invalid_otp' => self::invalidOtp(),
            'invalid_template' => self::invalidTemplate(),
            'balance_response' => self::balanceResponse(),
            'webhook_delivered' => self::webhookDelivered(),
            'webhook_failed' => self::webhookFailed(),
        ];
    }
}
