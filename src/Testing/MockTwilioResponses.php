<?php

namespace App\Messenger\Testing;

class MockTwilioResponses
{
    /**
     * Mock successful SMS response
     */
    public static function successfulSms(): array
    {
        return [
            'sid' => 'SM' . uniqid(),
            'date_created' => now()->toISOString(),
            'date_updated' => now()->toISOString(),
            'date_sent' => now()->toISOString(),
            'account_sid' => 'AC' . str_repeat('0', 32),
            'to' => '+201234567890',
            'from' => '+15551234567',
            'messaging_service_sid' => null,
            'body' => 'Test message',
            'status' => 'sent',
            'num_segments' => '1',
            'num_media' => '0',
            'direction' => 'outbound-api',
            'api_version' => '2010-04-01',
            'price' => '-0.00750',
            'price_unit' => 'USD',
            'error_code' => null,
            'error_message' => null,
            'uri' => '/2010-04-01/Accounts/AC.../Messages/SM...',
            'subresource_uris' => [
                'media' => '/2010-04-01/Accounts/AC.../Messages/SM.../Media.json',
            ],
        ];
    }

    /**
     * Mock successful WhatsApp response
     */
    public static function successfulWhatsApp(): array
    {
        return [
            'sid' => 'SM' . uniqid(),
            'date_created' => now()->toISOString(),
            'date_updated' => now()->toISOString(),
            'date_sent' => now()->toISOString(),
            'account_sid' => 'AC' . str_repeat('0', 32),
            'to' => 'whatsapp:+201234567890',
            'from' => 'whatsapp:+14155238886',
            'messaging_service_sid' => null,
            'body' => 'Hello from WhatsApp!',
            'status' => 'sent',
            'num_segments' => '1',
            'num_media' => '0',
            'direction' => 'outbound-api',
            'api_version' => '2010-04-01',
            'price' => null,
            'price_unit' => 'USD',
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * Mock authentication error (401)
     */
    public static function authenticationError(): array
    {
        return [
            'code' => 20003,
            'message' => 'Authenticate',
            'more_info' => 'https://www.twilio.com/docs/errors/20003',
            'status' => 401,
        ];
    }

    /**
     * Mock insufficient funds error
     */
    public static function insufficientFunds(): array
    {
        return [
            'code' => 21606,
            'message' => 'The From phone number provided is not a valid, SMS-capable inbound phone number or short code for your account.',
            'more_info' => 'https://www.twilio.com/docs/errors/21606',
            'status' => 400,
        ];
    }

    /**
     * Mock invalid phone number error
     */
    public static function invalidPhoneNumber(): array
    {
        return [
            'code' => 21211,
            'message' => 'The \'To\' number +1234567890 is not a valid phone number.',
            'more_info' => 'https://www.twilio.com/docs/errors/21211',
            'status' => 400,
        ];
    }

    /**
     * Mock rate limit error
     */
    public static function rateLimitError(): array
    {
        return [
            'code' => 20429,
            'message' => 'Too Many Requests',
            'more_info' => 'https://www.twilio.com/docs/errors/20429',
            'status' => 429,
        ];
    }

    /**
     * Mock blocked message error
     */
    public static function blockedMessage(): array
    {
        return [
            'code' => 21610,
            'message' => 'Message filtered',
            'more_info' => 'https://www.twilio.com/docs/errors/21610',
            'status' => 400,
        ];
    }

    /**
     * Mock webhook status callback - delivered
     */
    public static function webhookDelivered(): array
    {
        return [
            'MessageSid' => 'SM' . uniqid(),
            'MessageStatus' => 'delivered',
            'To' => '+201234567890',
            'From' => '+15551234567',
            'Body' => 'Test message',
            'NumSegments' => '1',
            'NumMedia' => '0',
            'AccountSid' => 'AC' . str_repeat('0', 32),
            'ApiVersion' => '2010-04-01',
            'EventType' => 'delivered',
        ];
    }

    /**
     * Mock webhook status callback - failed
     */
    public static function webhookFailed(): array
    {
        return [
            'MessageSid' => 'SM' . uniqid(),
            'MessageStatus' => 'failed',
            'To' => '+201234567890',
            'From' => '+15551234567',
            'Body' => 'Test message',
            'NumSegments' => '1',
            'NumMedia' => '0',
            'AccountSid' => 'AC' . str_repeat('0', 32),
            'ApiVersion' => '2010-04-01',
            'EventType' => 'failed',
            'ErrorCode' => '30008',
            'ErrorMessage' => 'Unknown error',
        ];
    }

    /**
     * Mock webhook status callback - undelivered
     */
    public static function webhookUndelivered(): array
    {
        return [
            'MessageSid' => 'SM' . uniqid(),
            'MessageStatus' => 'undelivered',
            'To' => '+201234567890',
            'From' => '+15551234567',
            'Body' => 'Test message',
            'NumSegments' => '1',
            'NumMedia' => '0',
            'AccountSid' => 'AC' . str_repeat('0', 32),
            'ApiVersion' => '2010-04-01',
            'EventType' => 'undelivered',
            'ErrorCode' => '30003',
            'ErrorMessage' => 'Unreachable destination handset',
        ];
    }

    /**
     * Mock webhook incoming message
     */
    public static function webhookIncoming(): array
    {
        return [
            'MessageSid' => 'SM' . uniqid(),
            'From' => '+201234567890',
            'To' => '+15551234567',
            'Body' => 'Thanks for the message!',
            'NumSegments' => '1',
            'NumMedia' => '0',
            'AccountSid' => 'AC' . str_repeat('0', 32),
            'ApiVersion' => '2010-04-01',
            'FromCity' => 'CAIRO',
            'FromState' => '',
            'FromCountry' => 'EG',
            'ToCity' => '',
            'ToState' => '',
            'ToCountry' => 'US',
        ];
    }

    /**
     * Get all mock responses for testing
     */
    public static function getAllMockResponses(): array
    {
        return [
            'successful_sms' => self::successfulSms(),
            'successful_whatsapp' => self::successfulWhatsApp(),
            'authentication_error' => self::authenticationError(),
            'insufficient_funds' => self::insufficientFunds(),
            'invalid_phone_number' => self::invalidPhoneNumber(),
            'rate_limit_error' => self::rateLimitError(),
            'blocked_message' => self::blockedMessage(),
            'webhook_delivered' => self::webhookDelivered(),
            'webhook_failed' => self::webhookFailed(),
            'webhook_undelivered' => self::webhookUndelivered(),
            'webhook_incoming' => self::webhookIncoming(),
        ];
    }
}
