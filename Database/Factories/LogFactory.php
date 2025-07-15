<?php

namespace Ihabrouk\Messenger\Database\Factories;

use Ihabrouk\Messenger\Models\Log;
use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Log Factory
 */
class LogFactory extends Factory
{
    protected $model = Log::class;

    public function definition(): array
    {
        $level = $this->faker->randomElement(['debug', 'info', 'warning', 'error']);
        $event = $this->faker->randomElement([
            'message_sent', 'message_delivered', 'message_failed', 'webhook_received',
            'batch_started', 'batch_completed', 'provider_error', 'rate_limit_hit'
        ]);

        $occurredAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $duration = $this->faker->optional(0.7)->randomFloat(2, 50.0, 2000.0);

        return [
            'log_id' => 'log_' . $this->faker->unique()->uuid(),
            'level' => $level,
            'event' => $event,
            'message' => $this->faker->sentence(),
            'context' => $this->faker->optional(0.6)->randomElements([
                'user_id' => $this->faker->numberBetween(1, 1000),
                'action' => $this->faker->word(),
                'ip_address' => $this->faker->ipv4(),
            ]),
            'provider' => $this->faker->optional(0.8)->randomElement(['sms_misr', 'twilio', 'mock_test']),
            'provider_message_id' => $this->faker->optional(0.7)->uuid(),
            'provider_response_code' => $this->faker->optional(0.6)->numberBetween(200, 500),
            'request_data' => $this->faker->optional(0.5)->randomElements([
                'to' => $this->faker->phoneNumber(),
                'from' => $this->faker->phoneNumber(),
                'body' => $this->faker->sentence(),
            ]),
            'response_data' => $this->faker->optional(0.5)->randomElements([
                'sid' => $this->faker->uuid(),
                'status' => $this->faker->randomElement(['sent', 'delivered', 'failed']),
                'price' => $this->faker->randomFloat(4, 0.01, 1.00),
            ]),
            'headers' => $this->faker->optional(0.4)->randomElements([
                'Content-Type' => 'application/json',
                'User-Agent' => $this->faker->userAgent(),
                'Authorization' => 'Bearer ' . $this->faker->sha256(),
            ]),
            'duration_ms' => $duration,
            'occurred_at' => $occurredAt,
            'error_code' => $level === 'error' ? $this->faker->randomElement(['30007', '30008', '21211', '500']) : null,
            'error_message' => $level === 'error' ? $this->faker->sentence() : null,
            'stack_trace' => $level === 'error' ? $this->faker->optional(0.7)->text(500) : null,
            'webhook_signature' => $this->faker->optional(0.3)->sha256(),
            'webhook_verified' => $this->faker->optional(0.3)->boolean(),
            'webhook_event' => $this->faker->optional(0.3)->randomElement(['delivered', 'failed', 'clicked']),
            'metadata' => $this->faker->optional(0.4)->randomElements([
                'session_id' => $this->faker->uuid(),
                'correlation_id' => $this->faker->uuid(),
                'trace_id' => $this->faker->uuid(),
            ]),
            'user_agent' => $this->faker->optional(0.6)->userAgent(),
            'ip_address' => $this->faker->optional(0.6)->ipv4(),
        ];
    }

    /**
     * Debug level log
     */
    public function debug(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'debug',
            'message' => 'Debug: ' . $this->faker->sentence(),
            'error_code' => null,
            'error_message' => null,
            'stack_trace' => null,
        ]);
    }

    /**
     * Info level log
     */
    public function info(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'info',
            'message' => 'Info: ' . $this->faker->sentence(),
            'error_code' => null,
            'error_message' => null,
            'stack_trace' => null,
        ]);
    }

    /**
     * Warning level log
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'warning',
            'message' => 'Warning: ' . $this->faker->sentence(),
            'error_code' => $this->faker->optional(0.5)->randomElement(['WARN_001', 'WARN_002']),
            'error_message' => $this->faker->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Error level log
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'error',
            'message' => 'Error: ' . $this->faker->sentence(),
            'error_code' => $this->faker->randomElement(['30007', '30008', '21211', '500', 'ERR_001']),
            'error_message' => $this->faker->sentence(),
            'stack_trace' => $this->faker->text(500),
        ]);
    }

    /**
     * Message related log
     */
    public function forMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_id' => Message::factory(),
            'event' => $this->faker->randomElement(['message_sent', 'message_delivered', 'message_failed']),
        ]);
    }

    /**
     * Batch related log
     */
    public function forBatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => Batch::factory(),
            'event' => $this->faker->randomElement(['batch_started', 'batch_completed', 'batch_failed']),
        ]);
    }

    /**
     * Webhook related log
     */
    public function webhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'webhook_received',
            'webhook_signature' => $this->faker->sha256(),
            'webhook_verified' => $this->faker->boolean(80),
            'webhook_event' => $this->faker->randomElement(['delivered', 'failed', 'clicked', 'replied']),
        ]);
    }

    /**
     * Provider related log
     */
    public function provider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'provider_message_id' => $this->faker->uuid(),
            'provider_response_code' => $this->faker->numberBetween(200, 500),
        ]);
    }

    /**
     * SMS Misr provider log
     */
    public function smsMisr(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'sms_misr',
            'provider_message_id' => 'sms_misr_' . $this->faker->uuid(),
            'provider_response_code' => $this->faker->randomElement([200, 400, 401, 429, 500]),
            'request_data' => [
                'username' => 'api_user',
                'password' => '***',
                'to' => $this->faker->phoneNumber(),
                'message' => $this->faker->sentence(),
            ],
            'response_data' => [
                'messageId' => $this->faker->uuid(),
                'status' => $this->faker->randomElement(['sent', 'failed']),
                'cost' => $this->faker->randomFloat(4, 0.01, 0.50),
            ],
        ]);
    }

    /**
     * Twilio provider log
     */
    public function twilio(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'twilio',
            'provider_message_id' => 'twilio_' . $this->faker->uuid(),
            'provider_response_code' => $this->faker->randomElement([200, 201, 400, 401, 429, 500]),
            'request_data' => [
                'To' => $this->faker->phoneNumber(),
                'From' => $this->faker->phoneNumber(),
                'Body' => $this->faker->sentence(),
            ],
            'response_data' => [
                'sid' => $this->faker->uuid(),
                'status' => $this->faker->randomElement(['queued', 'sent', 'delivered', 'failed']),
                'price' => $this->faker->randomFloat(4, 0.01, 1.00),
            ],
        ]);
    }

    /**
     * Recent log
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Slow request log
     */
    public function slow(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_ms' => $this->faker->randomFloat(2, 1500.0, 5000.0),
            'level' => 'warning',
            'message' => 'Slow request detected',
        ]);
    }

    /**
     * Fast request log
     */
    public function fast(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_ms' => $this->faker->randomFloat(2, 10.0, 200.0),
        ]);
    }

    /**
     * Rate limit log
     */
    public function rateLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'warning',
            'event' => 'rate_limit_hit',
            'message' => 'Rate limit exceeded for provider',
            'error_code' => '429',
            'error_message' => 'Too Many Requests',
        ]);
    }

    /**
     * Authentication error log
     */
    public function authError(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'error',
            'event' => 'authentication_failed',
            'message' => 'Authentication failed for provider',
            'error_code' => '401',
            'error_message' => 'Unauthorized',
            'provider_response_code' => 401,
        ]);
    }

    /**
     * Network error log
     */
    public function networkError(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'error',
            'event' => 'network_error',
            'message' => 'Network connection failed',
            'error_code' => 'NETWORK_ERROR',
            'error_message' => 'Could not connect to provider',
        ]);
    }

    /**
     * Success log
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'info',
            'event' => $this->faker->randomElement(['message_sent', 'message_delivered', 'batch_completed']),
            'message' => 'Operation completed successfully',
            'error_code' => null,
            'error_message' => null,
            'stack_trace' => null,
            'provider_response_code' => $this->faker->randomElement([200, 201]),
        ]);
    }
}
