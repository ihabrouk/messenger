<?php

namespace App\Messenger\Drivers;

use App\Messenger\Contracts\ProviderDefinitionInterface;
use App\Messenger\Data\SendMessageData;
use App\Messenger\Data\MessageResponse;
use App\Messenger\Data\ProviderDefinition;
use App\Messenger\Enums\MessageType;
use App\Messenger\Enums\MessageLanguage;
use App\Messenger\Exceptions\ProviderExceptionFactory;

class TwilioDriver extends AbstractProvider implements ProviderDefinitionInterface
{
    protected const API_VERSION = '2010-04-01';
    protected const BASE_URL = 'https://api.twilio.com';

    /**
     * Get provider definition for dynamic registration
     */
    public static function getProviderDefinition(): ProviderDefinition
    {
        return new ProviderDefinition(
            name: 'twilio',
            displayName: 'Twilio',
            description: 'Twilio SMS and WhatsApp provider',
            capabilities: [
                'sms',
                'whatsapp',
                'bulk_messaging',
            ],
            requiredConfig: ['account_sid', 'auth_token', 'from_number'],
            optionalConfig: ['whatsapp_from', 'webhook_secret', 'status_callback']
        );
    }

    /**
     * Send a single message
     */
    public function send(SendMessageData $data): MessageResponse
    {
        $this->validateConfig();
        $this->logActivity('Sending message', ['to' => $data->to, 'type' => $data->type->value]);

        $url = $this->getApiUrl($data);
        $payload = $this->buildPayload($data);
        $headers = $this->buildHeaders();

        $response = $this->makeRequest('POST', $url, [
            'form_params' => $payload,
            'headers' => $headers,
        ]);

        return $this->parseProviderResponse($response, [
            'to' => $data->to,
            'message' => $data->message,
            'type' => $data->type->value,
        ]);
    }

    /**
     * Send bulk messages (Twilio doesn't support bulk, so send individually)
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
     * Check account balance
     */
    public function getBalance(): float
    {
        $this->validateConfig();

        try {
            $url = $this->buildAccountUrl('Balance.json');
            $response = $this->makeRequest('GET', $url, [
                'headers' => $this->buildHeaders(),
            ]);

            return (float) ($response['balance'] ?? 0.0);
        } catch (\Exception $e) {
            $this->logActivity('Balance check failed', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Verify webhook signature using Twilio's validation
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        if (empty($this->config['webhook_secret'])) {
            return false;
        }

        // Parse the payload as form data
        parse_str($payload, $params);

        // Get the webhook URL from request
        $url = request()->url();

        return $this->validateTwilioSignature($signature, $url, $params);
    }

    /**
     * Process webhook payload from Twilio
     */
    public function processWebhook(array $payload): array
    {
        return [
            'message_id' => $payload['MessageSid'] ?? $payload['SmsSid'] ?? null,
            'status' => $this->mapWebhookStatus($payload['MessageStatus'] ?? $payload['SmsStatus'] ?? ''),
            'provider_response' => $payload,
            'delivered_at' => isset($payload['DateSent']) ? new \DateTime($payload['DateSent']) : null,
            'error_code' => $payload['ErrorCode'] ?? null,
            'error_message' => $payload['ErrorMessage'] ?? null,
        ];
    }

    /**
     * Get supported message types
     */
    public function getSupportedTypes(): array
    {
        return [MessageType::SMS, MessageType::WHATSAPP];
    }

    /**
     * Check if provider is healthy
     */
    public function isHealthy(): bool
    {
        try {
            $url = $this->buildAccountUrl();
            $response = $this->makeRequest('GET', $url, [
                'headers' => $this->buildHeaders(),
            ]);

            return isset($response['sid']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get required configuration keys
     */
    protected function getRequiredConfigKeys(): array
    {
        return ['account_sid', 'auth_token', 'from'];
    }

    /**
     * Extract response code from provider response
     */
    protected function extractResponseCode(array $response): string
    {
        if (isset($response['error_code'])) {
            return (string) $response['error_code'];
        }

        if (isset($response['status'])) {
            return $response['status'] === 'queued' || $response['status'] === 'sent' ? 'SUCCESS' : 'ERROR';
        }

        return 'SUCCESS';
    }

    /**
     * Extract provider ID from response
     */
    protected function extractProviderId(array $response): string
    {
        return $response['sid'] ?? $response['message_sid'] ?? uniqid('twilio_');
    }

    /**
     * Extract message ID from response
     */
    protected function extractMessageId(array $response): ?string
    {
        return $response['sid'] ?? $response['message_sid'] ?? null;
    }

    /**
     * Extract cost from response
     */
    protected function extractCost(array $response): ?float
    {
        $price = $response['price'] ?? null;
        return $price ? abs((float) $price) : null;
    }

    /**
     * Build request payload for Twilio
     */
    protected function buildPayload(SendMessageData $data): array
    {
        $payload = [
            'To' => $this->formatPhoneNumber($data->to, $data->type),
            'From' => $this->getFromNumber($data->type),
            'Body' => $data->message,
        ];

        // Add WhatsApp-specific fields
        if ($data->type === MessageType::WHATSAPP) {
            $payload = array_merge($payload, $this->buildWhatsAppPayload($data));
        }

        // Add status callback URL if configured
        if (!empty($this->config['webhook_secret'])) {
            $payload['StatusCallback'] = $this->getWebhookUrl();
        }

        return $payload;
    }

    /**
     * Build WhatsApp-specific payload
     */
    protected function buildWhatsAppPayload(SendMessageData $data): array
    {
        $payload = [];

        // Add media URLs if present in metadata
        if (isset($data->metadata['media_urls']) && !empty($data->metadata['media_urls'])) {
            $mediaUrls = (array) $data->metadata['media_urls'];
            foreach ($mediaUrls as $index => $url) {
                $payload["MediaUrl{$index}"] = $url;
            }
        }

        return $payload;
    }

    /**
     * Build request headers with Basic Auth
     */
    protected function buildHeaders(): array
    {
        $credentials = base64_encode($this->config['account_sid'] . ':' . $this->config['auth_token']);

        return [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get the API endpoint URL
     */
    protected function getApiUrl(SendMessageData $data): string
    {
        return $this->buildAccountUrl('Messages.json');
    }

    /**
     * Build account-specific URL
     */
    protected function buildAccountUrl(string $resource = ''): string
    {
        $baseUrl = self::BASE_URL . '/' . self::API_VERSION . '/Accounts/' . $this->config['account_sid'];

        return $resource ? $baseUrl . '/' . $resource : $baseUrl . '.json';
    }

    /**
     * Format phone number for Twilio
     */
    protected function formatPhoneNumber(string $phone, MessageType $type): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if missing
        if (!str_starts_with($phone, '+')) {
            // Default to Egypt country code if not specified
            if (!str_starts_with($phone, '2')) {
                $phone = '2' . ltrim($phone, '0');
            }
            $phone = '+' . $phone;
        }

        // Add WhatsApp prefix if needed
        if ($type === MessageType::WHATSAPP && !str_starts_with($phone, 'whatsapp:')) {
            $phone = 'whatsapp:' . $phone;
        }

        return $phone;
    }

    /**
     * Get the from number based on message type
     */
    protected function getFromNumber(MessageType $type): string
    {
        $from = $this->config['from'];

        if ($type === MessageType::WHATSAPP) {
            // Ensure WhatsApp format
            if (!str_starts_with($from, 'whatsapp:')) {
                $from = 'whatsapp:' . $from;
            }
        }

        return $from;
    }

    /**
     * Get webhook URL for status callbacks
     */
    protected function getWebhookUrl(): string
    {
        $baseUrl = config('app.url');
        return $baseUrl . '/messenger/webhook/twilio';
    }

    /**
     * Validate Twilio webhook signature
     */
    protected function validateTwilioSignature(string $signature, string $url, array $params): bool
    {
        $authToken = $this->config['auth_token'];

        // Sort parameters
        ksort($params);

        // Create the signature string
        $signatureString = $url;
        foreach ($params as $key => $value) {
            $signatureString .= $key . $value;
        }

        // Generate expected signature
        $expectedSignature = base64_encode(hash_hmac('sha1', $signatureString, $authToken, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Map webhook status to internal status
     */
    protected function mapWebhookStatus(string $status): string
    {
        return match (strtolower($status)) {
            'delivered' => 'delivered',
            'sent' => 'sent',
            'failed', 'undelivered' => 'failed',
            'queued', 'accepted' => 'queued',
            'sending' => 'sending',
            'received' => 'delivered', // For incoming messages
            default => 'unknown',
        };
    }

    /**
     * Check if response indicates success
     */
    protected function isSuccessResponse(string $responseCode): bool
    {
        // For Twilio, we check if there's no error_code in response
        return $responseCode === 'SUCCESS' || is_numeric($responseCode) === false;
    }
}
