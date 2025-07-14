<?php

namespace App\Messenger\Database\Factories;

use App\Messenger\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Contact Factory
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $phoneNumber = $this->faker->phoneNumber();

        // Clean phone number and add country code
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phoneNumber);
        if (!str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+1' . $cleanPhone; // Default to US
        }

        $isOptedIn = $this->faker->boolean(85); // 85% opted in
        $isVerified = $isOptedIn ? $this->faker->boolean(70) : false; // 70% of opted in are verified

        return [
            'contact_id' => 'contact_' . $this->faker->unique()->uuid(),
            'phone_number' => $cleanPhone,
            'formatted_phone' => $cleanPhone,
            'country_code' => substr($cleanPhone, 1, 2),
            'email' => $this->faker->optional(0.7)->email(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName . ' ' . $lastName,
            'language' => $this->faker->randomElement(['en', 'ar', 'fr', 'es']),
            'timezone' => $this->faker->timezone(),
            'is_opted_in' => $isOptedIn,
            'opted_in_at' => $isOptedIn ? $this->faker->dateTimeBetween('-1 year', 'now') : null,
            'opted_out_at' => !$isOptedIn ? $this->faker->dateTimeBetween('-6 months', 'now') : null,
            'opt_in_source' => $isOptedIn ? $this->faker->randomElement(['website', 'app', 'manual', 'import', 'api']) : null,
            'opt_out_reason' => !$isOptedIn ? $this->faker->randomElement(['user_request', 'spam_complaint', 'bounce', 'manual']) : null,
            'is_verified' => $isVerified,
            'verified_at' => $isVerified ? $this->faker->dateTimeBetween('-6 months', 'now') : null,
            'verification_code' => !$isVerified ? str_pad($this->faker->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT) : null,
            'verification_attempts' => !$isVerified ? $this->faker->numberBetween(0, 3) : 0,
            'last_verification_at' => !$isVerified ? $this->faker->optional(0.5)->dateTimeBetween('-7 days', 'now') : null,
            'tags' => $this->faker->optional(0.6)->randomElements(['vip', 'customer', 'lead', 'subscriber', 'employee'], 2),
            'preferences' => $this->faker->optional(0.5)->randomElements([
                'newsletter' => $this->faker->boolean(),
                'promotions' => $this->faker->boolean(),
                'reminders' => $this->faker->boolean(),
                'alerts' => $this->faker->boolean(),
                'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
            ]),
            'last_message_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
            'total_messages_sent' => $this->faker->numberBetween(0, 100),
            'total_messages_delivered' => $this->faker->numberBetween(0, 95),
            'last_delivery_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'is_blocked' => $this->faker->boolean(5), // 5% blocked
            'blocked_at' => null,
            'block_reason' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'external_id' => $this->faker->optional(0.4)->uuid(),
            'source' => $this->faker->randomElement(['website', 'app', 'manual', 'import', 'api', 'social']),
            'metadata' => $this->faker->optional(0.4)->randomElements([
                'acquisition_channel' => $this->faker->randomElement(['organic', 'paid', 'referral', 'direct']),
                'customer_segment' => $this->faker->randomElement(['new', 'returning', 'vip', 'dormant']),
                'lifecycle_stage' => $this->faker->randomElement(['lead', 'customer', 'advocate', 'churned']),
            ]),
        ];
    }

    /**
     * Opted in contact
     */
    public function optedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_opted_in' => true,
            'opted_in_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'opted_out_at' => null,
            'opt_in_source' => $this->faker->randomElement(['website', 'app', 'manual', 'import']),
            'opt_out_reason' => null,
        ]);
    }

    /**
     * Opted out contact
     */
    public function optedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_opted_in' => false,
            'opted_out_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'opt_out_reason' => $this->faker->randomElement(['user_request', 'spam_complaint', 'bounce']),
        ]);
    }

    /**
     * Verified contact
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verified_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'verification_code' => null,
            'verification_attempts' => 0,
        ]);
    }

    /**
     * Unverified contact
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'verified_at' => null,
            'verification_code' => str_pad($this->faker->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT),
            'verification_attempts' => $this->faker->numberBetween(0, 3),
            'last_verification_at' => $this->faker->optional(0.5)->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Blocked contact
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocked' => true,
            'blocked_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'block_reason' => $this->faker->randomElement(['spam_complaint', 'abuse', 'manual_block', 'system_block']),
        ]);
    }

    /**
     * Active contact (opted in and not blocked)
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_opted_in' => true,
            'is_blocked' => false,
            'opted_in_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'blocked_at' => null,
            'block_reason' => null,
        ]);
    }

    /**
     * Contact with specific language
     */
    public function language(string $language): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => $language,
        ]);
    }

    /**
     * English speaking contact
     */
    public function english(): static
    {
        return $this->language('en');
    }

    /**
     * Arabic speaking contact
     */
    public function arabic(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => 'ar',
            'first_name' => $this->faker->randomElement(['أحمد', 'محمد', 'علي', 'فاطمة', 'عائشة', 'خديجة']),
            'last_name' => $this->faker->randomElement(['الأحمد', 'المحمد', 'العلي', 'الحسن', 'الحسين']),
        ]);
    }

    /**
     * VIP contact
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => ['vip', 'premium'],
            'preferences' => [
                'newsletter' => true,
                'promotions' => true,
                'alerts' => true,
                'frequency' => 'daily',
                'priority' => 'high',
            ],
            'metadata' => [
                'customer_segment' => 'vip',
                'lifecycle_stage' => 'advocate',
                'total_spent' => $this->faker->randomFloat(2, 1000, 10000),
            ],
        ]);
    }

    /**
     * Customer contact
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => ['customer'],
            'total_messages_sent' => $this->faker->numberBetween(10, 50),
            'total_messages_delivered' => function (array $attributes) {
                return $this->faker->numberBetween(8, $attributes['total_messages_sent']);
            },
            'metadata' => [
                'customer_segment' => 'returning',
                'lifecycle_stage' => 'customer',
                'total_orders' => $this->faker->numberBetween(1, 20),
            ],
        ]);
    }

    /**
     * Lead contact
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => ['lead'],
            'total_messages_sent' => $this->faker->numberBetween(0, 5),
            'total_messages_delivered' => function (array $attributes) {
                return $this->faker->numberBetween(0, $attributes['total_messages_sent']);
            },
            'metadata' => [
                'customer_segment' => 'new',
                'lifecycle_stage' => 'lead',
                'lead_source' => $this->faker->randomElement(['website', 'social', 'referral', 'paid']),
            ],
        ]);
    }

    /**
     * High activity contact
     */
    public function highActivity(): static
    {
        $totalSent = $this->faker->numberBetween(50, 200);

        return $this->state(fn (array $attributes) => [
            'total_messages_sent' => $totalSent,
            'total_messages_delivered' => $this->faker->numberBetween(intval($totalSent * 0.8), $totalSent),
            'last_message_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'last_delivery_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Low activity contact
     */
    public function lowActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_messages_sent' => $this->faker->numberBetween(0, 5),
            'total_messages_delivered' => $this->faker->numberBetween(0, 3),
            'last_message_at' => $this->faker->optional(0.3)->dateTimeBetween('-90 days', '-30 days'),
            'last_delivery_at' => $this->faker->optional(0.2)->dateTimeBetween('-90 days', '-30 days'),
        ]);
    }

    /**
     * Recently active contact
     */
    public function recentlyActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_message_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'last_delivery_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Dormant contact
     */
    public function dormant(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_message_at' => $this->faker->optional(0.5)->dateTimeBetween('-1 year', '-90 days'),
            'last_delivery_at' => $this->faker->optional(0.4)->dateTimeBetween('-1 year', '-90 days'),
            'metadata' => [
                'customer_segment' => 'dormant',
                'lifecycle_stage' => 'churned',
                'last_activity' => $this->faker->dateTimeBetween('-1 year', '-90 days')->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Contact with email
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $this->faker->email(),
        ]);
    }

    /**
     * Contact without email
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }

    /**
     * Contact from specific source
     */
    public function source(string $source): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => $source,
            'opt_in_source' => $source,
        ]);
    }

    /**
     * Website source
     */
    public function fromWebsite(): static
    {
        return $this->source('website');
    }

    /**
     * App source
     */
    public function fromApp(): static
    {
        return $this->source('app');
    }

    /**
     * Import source
     */
    public function fromImport(): static
    {
        return $this->source('import');
    }

    /**
     * US phone number
     */
    public function usPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_number' => '+1' . $this->faker->numerify('##########'),
            'formatted_phone' => '+1' . $this->faker->numerify('##########'),
            'country_code' => '1',
        ]);
    }

    /**
     * Egypt phone number
     */
    public function egyptPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_number' => '+20' . $this->faker->numerify('#########'),
            'formatted_phone' => '+20' . $this->faker->numerify('#########'),
            'country_code' => '20',
        ]);
    }

    /**
     * Contact with tags
     */
    public function withTags(array $tags): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_unique(array_merge($attributes['tags'] ?? [], $tags)),
        ]);
    }

    /**
     * Contact with preferences
     */
    public function withPreferences(array $preferences): static
    {
        return $this->state(fn (array $attributes) => [
            'preferences' => array_merge($attributes['preferences'] ?? [], $preferences),
        ]);
    }
}
