<?php

namespace Ihabrouk\Messenger\Drivers;

use Ihabrouk\Messenger\Contracts\ProviderDefinitionInterface;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Data\MessageResponse;
use Ihabrouk\Messenger\Data\ProviderDefinition;
use Ihabrouk\Messenger\Enums\MessageType;
use Ihabrouk\Messenger\Enums\MessageLanguage;
use Ihabrouk\Messenger\Exceptions\ProviderExceptionFactory;

class SmsMisrDriver extends AbstractProvider implements ProviderDefinitionInterface
{
    /**
     * Get provider definition for dynamic registration
     */
    public static function getProviderDefinition(): ProviderDefinition
    {
        return new ProviderDefinition(
            name: 'smsmisr',
            displayName: 'SMS Misr',
            description: 'SMS Misr provider for Egyptian market',
            capabilities: [
                'sms',
                'otp',
                'bulk_messaging',
            ],
            requiredConfig: ['username', 'password', 'sender_id'],
            optionalConfig: ['language', 'unicode', 'deliv_time', 'webhook_secret']
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

        $response = $this->makeRequest('POST', $url, [
            'form_params' => $payload,
        ]);

        return $this->parseProviderResponse($response, [
            'to' => $data->to,
            'message' => $data->message,
            'type' => $data->type->value,
        ]);
    }

    /**
     * Send bulk messages
     */
    public function sendBulk(array $messages): array
    {
        $results = [];
        $chunks = array_chunk($messages, $this->getMaxRecipients());

        foreach ($chunks as $chunk) {
            // For SMS Misr, we can send multiple recipients in one request
            if (count($chunk) > 1) {
                $results[] = $this->sendBulkChunk($chunk);
            } else {
                $results[] = $this->send($chunk[0]);
            }
        }

        return array_merge(...$results);
    }

    /**
     * Send a chunk of bulk messages
     */
    protected function sendBulkChunk(array $messages): array
    {
        $firstMessage = $messages[0];
        $recipients = array_map(fn($msg) => $msg->to, $messages);

        $bulkData = new SendMessageData(
            to: implode(',', $recipients),
            message: $firstMessage->message,
            type: $firstMessage->type,
            provider: $firstMessage->provider,
            language: $firstMessage->language,
            priority: $firstMessage->priority,
            variables: $firstMessage->variables,
            templateKey: $firstMessage->templateKey,
            templateId: $firstMessage->templateId,
            scheduledAt: $firstMessage->scheduledAt,
            reference: $firstMessage->reference,
            metadata: $firstMessage->metadata,
        );

        $response = $this->send($bulkData);

        // Create individual responses for each recipient
        $messageCount = count($messages);
        return array_map(
            fn($message) => new MessageResponse(
                success: $response->success,
                status: $response->status,
                providerId: $response->providerId,
                providerMessageId: $response->providerMessageId,
                cost: $response->cost ? $response->cost / $messageCount : null,
                errorCode: $response->errorCode,
                errorMessage: $response->errorMessage,
                metadata: array_merge($response->metadata, ['recipient' => $message->to]),
                provider: $response->provider,
                sentAt: $response->sentAt,
            ),
            $messages
        );
    }

    /**
     * Check account balance
     */
    public function getBalance(): float
    {
        $this->validateConfig();

        try {
            $response = $this->makeRequest('GET', $this->config['balance_url'], [
                'query' => [
                    'username' => $this->config['username'],
                    'password' => $this->config['password'],
                ],
            ]);

            return (float) ($response['Balance'] ?? 0.0);
        } catch (\Exception $e) {
            $this->logActivity('Balance check failed', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Verify webhook signature using HMAC
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        if (empty($this->config['webhook_secret'])) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->config['webhook_secret']);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook payload
     */
    public function processWebhook(array $payload): array
    {
        return [
            'message_id' => $payload['SMSID'] ?? null,
            'status' => $this->mapWebhookStatus($payload['Status'] ?? ''),
            'provider_response' => $payload,
            'delivered_at' => isset($payload['DeliveredAt']) ? new \DateTime($payload['DeliveredAt']) : null,
        ];
    }

    /**
     * Get supported message types
     */
    public function getSupportedTypes(): array
    {
        return [MessageType::SMS, MessageType::OTP];
    }

    /**
     * Check if provider is healthy
     */
    public function isHealthy(): bool
    {
        try {
            $this->getBalance();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get required configuration keys
     */
    protected function getRequiredConfigKeys(): array
    {
        return ['username', 'password', 'sender_id', 'api_base_url'];
    }

    /**
     * Extract response code from provider response
     */
    protected function extractResponseCode(array $response): string
    {
        return (string) ($response['code'] ?? $response['Code'] ?? 'UNKNOWN');
    }

    /**
     * Extract provider ID from response
     */
    protected function extractProviderId(array $response): string
    {
        return (string) ($response['SMSID'] ?? $response['id'] ?? uniqid('sms_'));
    }

    /**
     * Extract message ID from response
     */
    protected function extractMessageId(array $response): ?string
    {
        return $response['SMSID'] ?? $response['id'] ?? null;
    }

    /**
     * Extract cost from response
     */
    protected function extractCost(array $response): ?float
    {
        $cost = $response['Cost'] ?? $response['cost'] ?? null;
        return $cost ? (float) $cost : null;
    }

    /**
     * Build request payload for SMS Misr
     */
    protected function buildPayload(SendMessageData $data): array
    {
        $payload = [
            'environment' => $this->config['environment'],
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'sender' => $this->getSenderId(),
            'mobile' => $this->formatPhoneNumber($data->to),
            'language' => $this->getLanguageCode($data->language),
            'message' => $this->prepareMessage($data->message, $data->language),
        ];

        // Add OTP-specific fields
        if ($data->type === MessageType::OTP && $data->otpCode) {
            $payload = array_merge($payload, $this->buildOtpPayload($data));
        }

        // Add scheduling if specified
        if ($data->scheduledAt) {
            $payload['DelayUntil'] = $data->scheduledAt->format('YmdHi');
        }

        return $payload;
    }

    /**
     * Build OTP-specific payload
     */
    protected function buildOtpPayload(SendMessageData $data): array
    {
        return [
            'template' => $data->otpTemplate ?? $this->getDefaultOtpTemplate($data->language),
            'otp' => $data->otpCode,
        ];
    }

    /**
     * Build request headers
     */
    protected function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get the API endpoint URL
     */
    protected function getApiUrl(SendMessageData $data): string
    {
        if ($data->type === MessageType::OTP) {
            return $this->config['otp_base_url'];
        }

        return $this->config['api_base_url'];
    }

    /**
     * Get sender ID based on environment
     */
    protected function getSenderId(): string
    {
        if ($this->config['environment'] == 2) { // Test environment
            return $this->config['test_sender_id'];
        }

        return $this->config['sender_id'];
    }

    /**
     * Format phone number for SMS Misr
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure Egyptian format (starts with 2)
        if (!str_starts_with($phone, '2')) {
            $phone = '2' . ltrim($phone, '0');
        }

        return $phone;
    }

    /**
     * Get language code for SMS Misr API
     */
    protected function getLanguageCode(MessageLanguage $language): int
    {
        return match ($language) {
            MessageLanguage::ENGLISH => 1,
            MessageLanguage::ARABIC => 2,
            MessageLanguage::UNICODE => 3,
        };
    }

    /**
     * Prepare message based on language
     */
    protected function prepareMessage(string $message, MessageLanguage $language): string
    {
        if ($language === MessageLanguage::ARABIC || $language === MessageLanguage::UNICODE) {
            return rawurlencode($message);
        }

        return $message;
    }

    /**
     * Get default OTP template based on language
     */
    protected function getDefaultOtpTemplate(MessageLanguage $language): string
    {
        return match ($language) {
            MessageLanguage::ARABIC => 'e83faf6025ec41d0f40256d2812629f5fa9291d05c8322f31eea834302501da8',
            MessageLanguage::ENGLISH => '0f9217c9d760c1c0ed47b8afb5425708da7d98729016a8accfc14f9cc8d1ba83',
            MessageLanguage::UNICODE => '0f9217c9d760c1c0ed47b8afb5425708da7d98729016a8accfc14f9cc8d1ba83',
        };
    }

    /**
     * Map webhook status to internal status
     */
    protected function mapWebhookStatus(string $status): string
    {
        return match (strtolower($status)) {
            'delivered' => 'delivered',
            'failed' => 'failed',
            'pending' => 'pending',
            default => 'unknown',
        };
    }
}
