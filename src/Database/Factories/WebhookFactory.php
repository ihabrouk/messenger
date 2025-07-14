<?php

namespace App\Messenger\Database\Factories;

use App\Messenger\Models\Webhook;
use App\Messenger\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Webhook Factory
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        $provider = $this->faker->randomElement(['sms_misr', 'twilio', 'mock_test']);
        $eventType = $this->faker->randomElement(['delivered', 'failed', 'clicked', 'replied', 'read']);
        $status = $this->faker->randomElement(['received', 'processing', 'processed', 'failed']);

        $rawPayload = $this->generatePayloadForProvider($provider, $eventType);

        $deliveredAt = in_array($eventType, ['delivered', 'delivery_confirmed']) ?
            $this->faker->dateTimeBetween('-24 hours', 'now') : null;

        $readAt = $eventType === 'read' ?
            $this->faker->dateTimeBetween('-24 hours', 'now') : null;

        return [
            'webhook_id' => 'webhook_' . $this->faker->unique()->uuid(),
            'provider' => $provider,
            'provider_message_id' => $this->faker->uuid(),
            'event_type' => $eventType,
            'status' => $status,
            'delivery_status' => $this->faker->optional(0.7)->randomElement(['pending', 'delivered', 'failed']),
            'delivered_at' => $deliveredAt,
            'read_at' => $readAt,
            'error_code' => in_array($eventType, ['failed', 'rejected']) ?
                $this->faker->randomElement(['30007', '30008', '21211']) : null,
            'error_message' => in_array($eventType, ['failed', 'rejected']) ?
                $this->faker->sentence() : null,
            'raw_payload' => $rawPayload,
            'processed_payload' => $status === 'processed' ? $this->processPayload($rawPayload, $provider) : null,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => $this->faker->userAgent(),
                'X-Signature' => $this->faker->sha256(),
            ],
            'signature' => $this->faker->optional(0.8)->sha256(),
            'is_verified' => $this->faker->boolean(85),
            'verification_attempts' => $this->faker->numberBetween(0, 3),
            'processed' => $status === 'processed',
            'processed_at' => $status === 'processed' ? $this->faker->dateTimeBetween('-24 hours', 'now') : null,
            'failure_reason' => $status === 'failed' ? $this->faker->sentence() : null,
            'retry_count' => $status === 'failed' ? $this->faker->numberBetween(1, 3) : 0,
            'next_retry_at' => $status === 'failed' ? $this->faker->dateTimeBetween('now', '+1 hour') : null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'metadata' => $this->faker->optional(0.4)->randomElements([
                'source' => $this->faker->randomElement(['webhook', 'api', 'manual']),
                'environment' => $this->faker->randomElement(['production', 'staging', 'development']),
            ]),
        ];
    }

    /**
     * Generate payload for specific provider
     */
    protected function generatePayloadForProvider(string $provider, string $eventType): array
    {
        return match($provider) {
            'sms_misr' => $this->generateSMSMisrPayload($eventType),
            'twilio' => $this->generateTwilioPayload($eventType),
            'mock_test' => $this->generateMockPayload($eventType),
            default => ['event' => $eventType, 'timestamp' => now()->toISOString()],
        };
    }

    /**
     * Generate SMS Misr webhook payload
     */
    protected function generateSMSMisrPayload(string $eventType): array
    {
        return [
            'messageId' => $this->faker->uuid(),
            'status' => $eventType,
            'to' => $this->faker->phoneNumber(),
            'from' => $this->faker->phoneNumber(),
            'timestamp' => now()->toISOString(),
            'deliveredAt' => in_array($eventType, ['delivered', 'delivery_confirmed']) ?
                now()->toISOString() : null,
            'cost' => $this->faker->randomFloat(4, 0.01, 0.50),
            'currency' => 'USD',
        ];
    }

    /**
     * Generate Twilio webhook payload
     */
    protected function generateTwilioPayload(string $eventType): array
    {
        $status = match($eventType) {
            'delivered' => 'delivered',
            'failed' => 'failed',
            'read' => 'delivered',
            default => $eventType,
        };

        return [
            'MessageSid' => 'SM' . $this->faker->randomNumber(8, true) . $this->faker->randomNumber(8, true),
            'MessageStatus' => $status,
            'To' => $this->faker->phoneNumber(),
            'From' => $this->faker->phoneNumber(),
            'Body' => $this->faker->sentence(),
            'ErrorCode' => in_array($eventType, ['failed', 'rejected']) ?
                $this->faker->randomElement(['30007', '30008', '21211']) : null,
            'ErrorMessage' => in_array($eventType, ['failed', 'rejected']) ?
                $this->faker->sentence() : null,
            'Price' => $this->faker->randomFloat(4, 0.01, 1.00),
            'PriceUnit' => 'USD',
        ];
    }

    /**
     * Generate mock test payload
     */
    protected function generateMockPayload(string $eventType): array
    {
        return [
            'id' => $this->faker->uuid(),
            'event' => $eventType,
            'message_id' => $this->faker->uuid(),
            'status' => $eventType,
            'timestamp' => now()->toISOString(),
            'recipient' => $this->faker->phoneNumber(),
            'cost' => 0.00,
            'test_mode' => true,
        ];
    }

    /**
     * Process payload for display
     */
    protected function processPayload(array $rawPayload, string $provider): array
    {
        return match($provider) {
            'sms_misr' => [
                'message_id' => $rawPayload['messageId'] ?? null,
                'status' => $rawPayload['status'] ?? null,
                'delivered_at' => $rawPayload['deliveredAt'] ?? null,
                'cost' => $rawPayload['cost'] ?? null,
            ],
            'twilio' => [
                'message_sid' => $rawPayload['MessageSid'] ?? null,
                'status' => $rawPayload['MessageStatus'] ?? null,
                'error_code' => $rawPayload['ErrorCode'] ?? null,
                'price' => $rawPayload['Price'] ?? null,
            ],
            'mock_test' => [
                'id' => $rawPayload['id'] ?? null,
                'event' => $rawPayload['event'] ?? null,
                'test_mode' => $rawPayload['test_mode'] ?? false,
            ],
            default => $rawPayload,
        };
    }

    /**
     * Delivered webhook
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'delivered',
            'delivery_status' => 'delivered',
            'delivered_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'status' => 'processed',
            'processed' => true,
            'processed_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Failed webhook
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'failed',
            'delivery_status' => 'failed',
            'error_code' => $this->faker->randomElement(['30007', '30008', '21211']),
            'error_message' => $this->faker->sentence(),
            'status' => 'processed',
            'processed' => true,
            'processed_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Read webhook
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'read',
            'read_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'status' => 'processed',
            'processed' => true,
            'processed_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Clicked webhook
     */
    public function clicked(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'clicked',
            'status' => 'processed',
            'processed' => true,
            'processed_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Replied webhook
     */
    public function replied(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'replied',
            'status' => 'processed',
            'processed' => true,
            'processed_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Verified webhook
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verification_attempts' => 1,
        ]);
    }

    /**
     * Unverified webhook
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'verification_attempts' => $this->faker->numberBetween(1, 5),
        ]);
    }

    /**
     * Processed webhook
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'processed' => true,
            'processed_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'failure_reason' => null,
            'retry_count' => 0,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Unprocessed webhook
     */
    public function unprocessed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'received',
            'processed' => false,
            'processed_at' => null,
        ]);
    }

    /**
     * Processing webhook
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed' => false,
            'processed_at' => null,
        ]);
    }

    /**
     * Failed processing webhook
     */
    public function processingFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'processed' => false,
            'processed_at' => null,
            'failure_reason' => $this->faker->sentence(),
            'retry_count' => $this->faker->numberBetween(1, 3),
            'next_retry_at' => $this->faker->dateTimeBetween('now', '+1 hour'),
        ]);
    }

    /**
     * For specific provider
     */
    public function provider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'raw_payload' => $this->generatePayloadForProvider($provider, $attributes['event_type'] ?? 'delivered'),
        ]);
    }

    /**
     * SMS Misr webhook
     */
    public function smsMisr(): static
    {
        return $this->provider('sms_misr');
    }

    /**
     * Twilio webhook
     */
    public function twilio(): static
    {
        return $this->provider('twilio');
    }

    /**
     * Mock test webhook
     */
    public function mockTest(): static
    {
        return $this->provider('mock_test');
    }

    /**
     * For specific message
     */
    public function forMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_id' => Message::factory(),
        ]);
    }

    /**
     * Recent webhook
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Pending retry webhook
     */
    public function pendingRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed' => false,
            'retry_count' => $this->faker->numberBetween(1, 3),
            'next_retry_at' => $this->faker->dateTimeBetween('-30 minutes', 'now'),
            'failure_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Max retries reached
     */
    public function maxRetriesReached(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed' => false,
            'retry_count' => 5,
            'next_retry_at' => null,
            'failure_reason' => 'Maximum retry attempts reached',
            'status' => 'failed',
        ]);
    }
}
