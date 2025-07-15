<?php

namespace Ihabrouk\Messenger\Database\Factories;

use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Message Factory
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $sentAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $status = $this->faker->randomElement(['pending', 'sent', 'delivered', 'failed']);

        $deliveredAt = null;
        $readAt = null;

        if ($status === 'delivered') {
            $deliveredAt = $this->faker->dateTimeBetween($sentAt, 'now');

            // Sometimes messages are read
            if ($this->faker->boolean(30)) {
                $readAt = $this->faker->dateTimeBetween($deliveredAt, 'now');
            }
        }

        return [
            'message_id' => 'msg_' . $this->faker->unique()->uuid(),
            'recipient_phone' => $this->faker->phoneNumber(),
            'recipient_name' => $this->faker->name(),
            'content' => $this->faker->paragraph(),
            'provider' => $this->faker->randomElement(['sms_misr', 'twilio', 'mock_test']),
            'provider_message_id' => 'prov_' . $this->faker->uuid(),
            'status' => $status,
            'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'scheduled_at' => $this->faker->optional(0.2)->dateTimeBetween('now', '+7 days'),
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'read_at' => $readAt,
            'cost' => $this->faker->randomFloat(4, 0.01, 1.00),
            'currency' => 'USD',
            'direction' => $this->faker->randomElement(['outbound', 'inbound']),
            'message_type' => $this->faker->randomElement(['sms', 'mms']),
            'encoding' => $this->faker->randomElement(['gsm7', 'ucs2']),
            'parts_count' => $this->faker->numberBetween(1, 3),
            'character_count' => $this->faker->numberBetween(50, 500),
            'tags' => $this->faker->optional(0.5)->randomElements(['promotion', 'notification', 'reminder', 'alert'], 2),
            'metadata' => $this->faker->optional(0.3)->randomElements([
                'campaign_id' => $this->faker->uuid(),
                'user_id' => $this->faker->numberBetween(1, 1000),
                'source' => $this->faker->randomElement(['web', 'api', 'admin']),
            ]),
            'retry_count' => $status === 'failed' ? $this->faker->numberBetween(1, 3) : 0,
            'error_code' => $status === 'failed' ? $this->faker->randomElement(['30007', '30008', '21211']) : null,
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
        ];
    }

    /**
     * Indicate that the message is pending
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the message is sent
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'delivered_at' => null,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the message is delivered
     */
    public function delivered(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-24 hours', '-1 hour');
        $deliveredAt = $this->faker->dateTimeBetween($sentAt, 'now');

        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the message is read
     */
    public function read(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-24 hours', '-2 hours');
        $deliveredAt = $this->faker->dateTimeBetween($sentAt, '-1 hour');
        $readAt = $this->faker->dateTimeBetween($deliveredAt, 'now');

        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'read_at' => $readAt,
        ]);
    }

    /**
     * Indicate that the message failed
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'sent_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'delivered_at' => null,
            'read_at' => null,
            'retry_count' => $this->faker->numberBetween(1, 3),
            'error_code' => $this->faker->randomElement(['30007', '30008', '21211']),
            'error_message' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the message is scheduled
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
        ]);
    }

    /**
     * Set high priority
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Set low priority
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
        ]);
    }

    /**
     * Use specific provider
     */
    public function provider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
        ]);
    }

    /**
     * With template
     */
    public function withTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => Template::factory(),
        ]);
    }

    /**
     * With batch
     */
    public function withBatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => Batch::factory(),
        ]);
    }

    /**
     * Set expensive cost
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => $this->faker->randomFloat(4, 0.50, 2.00),
        ]);
    }

    /**
     * Set cheap cost
     */
    public function cheap(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => $this->faker->randomFloat(4, 0.01, 0.10),
        ]);
    }

    /**
     * Multi-part message
     */
    public function multiPart(): static
    {
        $parts = $this->faker->numberBetween(2, 5);

        return $this->state(fn (array $attributes) => [
            'parts_count' => $parts,
            'character_count' => $parts * 160,
            'content' => $this->faker->text($parts * 160),
        ]);
    }

    /**
     * Inbound message
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'inbound',
            'status' => 'received',
            'cost' => 0,
        ]);
    }

    /**
     * Recent message
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }
}
