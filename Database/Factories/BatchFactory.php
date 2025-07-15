<?php

namespace Ihabrouk\Messenger\Database\Factories;

use Ihabrouk\Messenger\Models\Batch;
use Ihabrouk\Messenger\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Batch Factory
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        $totalRecipients = $this->faker->numberBetween(10, 1000);
        $status = $this->faker->randomElement(['pending', 'processing', 'completed', 'failed', 'cancelled']);

        $sentCount = 0;
        $deliveredCount = 0;
        $failedCount = 0;

        if ($status === 'processing') {
            $sentCount = $this->faker->numberBetween(0, $totalRecipients);
            $deliveredCount = $this->faker->numberBetween(0, $sentCount);
            $failedCount = $this->faker->numberBetween(0, min(10, $totalRecipients - $sentCount));
        } elseif ($status === 'completed') {
            $sentCount = $totalRecipients;
            $deliveredCount = $this->faker->numberBetween(intval($totalRecipients * 0.8), $totalRecipients);
            $failedCount = $totalRecipients - $deliveredCount;
        } elseif ($status === 'failed') {
            $sentCount = $this->faker->numberBetween(0, intval($totalRecipients * 0.3));
            $deliveredCount = $this->faker->numberBetween(0, $sentCount);
            $failedCount = $this->faker->numberBetween(5, $totalRecipients);
        }

        $startedAt = $status !== 'pending' ? $this->faker->dateTimeBetween('-7 days', 'now') : null;
        $completedAt = $status === 'completed' ? $this->faker->dateTimeBetween($startedAt ?: '-7 days', 'now') : null;

        return [
            'batch_id' => 'batch_' . $this->faker->unique()->uuid(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional(0.7)->sentence(),
            'status' => $status,
            'total_recipients' => $totalRecipients,
            'sent_count' => $sentCount,
            'delivered_count' => $deliveredCount,
            'failed_count' => $failedCount,
            'scheduled_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+7 days'),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'cancelled_at' => $status === 'cancelled' ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
            'cancellation_reason' => $status === 'cancelled' ? $this->faker->sentence() : null,
            'estimated_cost' => $this->faker->randomFloat(2, 10.00, 500.00),
            'actual_cost' => $status === 'completed' ? $this->faker->randomFloat(2, 10.00, 500.00) : null,
            'currency' => 'USD',
            'rate_limit' => $this->faker->randomElement([10, 20, 50, 100]),
            'rate_limit_period' => $this->faker->randomElement(['minute', 'hour']),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'provider' => $this->faker->randomElement(['sms_misr', 'twilio', 'mock_test']),
            'tags' => $this->faker->optional(0.5)->randomElements(['campaign', 'newsletter', 'announcement', 'reminder'], 2),
            'metadata' => $this->faker->optional(0.4)->randomElements([
                'campaign_id' => $this->faker->uuid(),
                'created_by' => $this->faker->name(),
                'department' => $this->faker->randomElement(['marketing', 'support', 'sales']),
            ]),
            'retry_failed' => $this->faker->boolean(30),
            'max_retries' => $this->faker->numberBetween(1, 3),
            'current_retry' => 0,
        ];
    }

    /**
     * Indicate that the batch is pending
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'sent_count' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
            'started_at' => null,
            'completed_at' => null,
            'actual_cost' => null,
        ]);
    }

    /**
     * Indicate that the batch is processing
     */
    public function processing(): static
    {
        $totalRecipients = $this->faker->numberBetween(50, 500);
        $sentCount = $this->faker->numberBetween(1, intval($totalRecipients * 0.7));
        $deliveredCount = $this->faker->numberBetween(0, $sentCount);
        $failedCount = $this->faker->numberBetween(0, min(5, $sentCount - $deliveredCount));

        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'total_recipients' => $totalRecipients,
            'sent_count' => $sentCount,
            'delivered_count' => $deliveredCount,
            'failed_count' => $failedCount,
            'started_at' => $this->faker->dateTimeBetween('-2 hours', 'now'),
            'completed_at' => null,
            'actual_cost' => null,
        ]);
    }

    /**
     * Indicate that the batch is completed
     */
    public function completed(): static
    {
        $totalRecipients = $this->faker->numberBetween(10, 200);
        $deliveredCount = $this->faker->numberBetween(intval($totalRecipients * 0.8), $totalRecipients);
        $failedCount = $totalRecipients - $deliveredCount;
        $startedAt = $this->faker->dateTimeBetween('-7 days', '-1 hour');
        $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'total_recipients' => $totalRecipients,
            'sent_count' => $totalRecipients,
            'delivered_count' => $deliveredCount,
            'failed_count' => $failedCount,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'actual_cost' => $this->faker->randomFloat(2, 5.00, 100.00),
        ]);
    }

    /**
     * Indicate that the batch failed
     */
    public function failed(): static
    {
        $totalRecipients = $this->faker->numberBetween(10, 100);
        $sentCount = $this->faker->numberBetween(0, intval($totalRecipients * 0.3));
        $deliveredCount = $this->faker->numberBetween(0, $sentCount);
        $failedCount = $this->faker->numberBetween(5, $totalRecipients);

        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'total_recipients' => $totalRecipients,
            'sent_count' => $sentCount,
            'delivered_count' => $deliveredCount,
            'failed_count' => $failedCount,
            'started_at' => $this->faker->dateTimeBetween('-24 hours', '-1 hour'),
            'completed_at' => null,
            'actual_cost' => $sentCount > 0 ? $this->faker->randomFloat(2, 1.00, 20.00) : null,
        ]);
    }

    /**
     * Indicate that the batch is cancelled
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'cancellation_reason' => $this->faker->randomElement([
                'User cancelled',
                'Budget exceeded',
                'Provider issues',
                'Content rejected',
            ]),
            'sent_count' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
            'actual_cost' => null,
        ]);
    }

    /**
     * Indicate that the batch is scheduled
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'scheduled_at' => $this->faker->dateTimeBetween('+1 hour', '+7 days'),
            'started_at' => null,
        ]);
    }

    /**
     * Set high priority
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
            'rate_limit' => 100,
        ]);
    }

    /**
     * Set low priority
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
            'rate_limit' => 10,
        ]);
    }

    /**
     * Large batch
     */
    public function large(): static
    {
        $totalRecipients = $this->faker->numberBetween(1000, 10000);

        return $this->state(fn (array $attributes) => [
            'total_recipients' => $totalRecipients,
            'estimated_cost' => $this->faker->randomFloat(2, 100.00, 2000.00),
            'rate_limit' => 50,
        ]);
    }

    /**
     * Small batch
     */
    public function small(): static
    {
        $totalRecipients = $this->faker->numberBetween(1, 50);

        return $this->state(fn (array $attributes) => [
            'total_recipients' => $totalRecipients,
            'estimated_cost' => $this->faker->randomFloat(2, 0.50, 25.00),
            'rate_limit' => 100,
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
     * Marketing campaign
     */
    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Marketing Campaign - ' . $this->faker->words(2, true),
            'tags' => ['marketing', 'campaign'],
            'metadata' => [
                'campaign_type' => 'marketing',
                'department' => 'marketing',
                'budget' => $this->faker->randomFloat(2, 100.00, 1000.00),
            ],
        ]);
    }

    /**
     * Notification batch
     */
    public function notification(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'System Notification - ' . $this->faker->words(2, true),
            'tags' => ['notification', 'system'],
            'priority' => 'high',
            'metadata' => [
                'type' => 'notification',
                'department' => 'support',
            ],
        ]);
    }

    /**
     * Reminder batch
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Reminder - ' . $this->faker->words(2, true),
            'tags' => ['reminder'],
            'priority' => 'normal',
            'metadata' => [
                'type' => 'reminder',
                'automated' => true,
            ],
        ]);
    }

    /**
     * Expensive batch
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_cost' => $this->faker->randomFloat(2, 500.00, 2000.00),
            'actual_cost' => $this->faker->randomFloat(2, 500.00, 2000.00),
        ]);
    }

    /**
     * Cheap batch
     */
    public function cheap(): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_cost' => $this->faker->randomFloat(2, 1.00, 25.00),
            'actual_cost' => $this->faker->randomFloat(2, 1.00, 25.00),
        ]);
    }

    /**
     * Recent batch
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
        ]);
    }

    /**
     * With retry enabled
     */
    public function withRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_failed' => true,
            'max_retries' => $this->faker->numberBetween(2, 5),
        ]);
    }

    /**
     * Currently being retried
     */
    public function retrying(): static
    {
        $maxRetries = $this->faker->numberBetween(2, 5);
        $currentRetry = $this->faker->numberBetween(1, $maxRetries - 1);

        return $this->state(fn (array $attributes) => [
            'retry_failed' => true,
            'max_retries' => $maxRetries,
            'current_retry' => $currentRetry,
            'status' => 'processing',
        ]);
    }
}
