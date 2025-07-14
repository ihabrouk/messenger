<?php

namespace App\Messenger\Demo\Factories;

use App\Messenger\Demo\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Demo Contact Factory - FOR TESTING/DEMO PURPOSES ONLY
 *
 * ⚠️ IMPORTANT: This factory is for demonstration and testing purposes only.
 * In production, use your existing User/Member model factories.
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $phoneNumber = $this->faker->e164PhoneNumber();

        return [
            'contact_id' => 'contact_' . $this->faker->uuid(),
            'phone_number' => $phoneNumber,
            'formatted_phone' => $phoneNumber,
            'country_code' => $this->faker->randomElement(['20', '1', '44', '33', '49']),
            'email' => $this->faker->safeEmail(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName . ' ' . $lastName,
            'language' => $this->faker->randomElement(['en', 'ar']),
            'timezone' => $this->faker->timezone(),
            'is_opted_in' => $this->faker->boolean(80), // 80% opted in
            'opted_in_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year'),
            'opted_out_at' => null,
            'opt_in_source' => $this->faker->randomElement(['website', 'app', 'sms', 'manual']),
            'opt_out_reason' => null,
            'is_verified' => $this->faker->boolean(60), // 60% verified
            'verified_at' => $this->faker->optional(0.6)->dateTimeBetween('-6 months'),
            'verification_code' => null,
            'verification_attempts' => 0,
            'last_verification_at' => null,
            'tags' => $this->faker->randomElements(['vip', 'customer', 'prospect', 'member', 'guest'], rand(0, 3)),
            'preferences' => [
                'sms_enabled' => $this->faker->boolean(90),
                'whatsapp_enabled' => $this->faker->boolean(70),
                'marketing_enabled' => $this->faker->boolean(50),
                'notifications_enabled' => $this->faker->boolean(85),
            ],
            'last_message_at' => $this->faker->optional(0.7)->dateTimeBetween('-3 months'),
            'total_messages_sent' => $this->faker->numberBetween(0, 50),
            'total_messages_delivered' => function (array $attributes) {
                return $this->faker->numberBetween(0, $attributes['total_messages_sent']);
            },
            'last_delivery_at' => $this->faker->optional(0.6)->dateTimeBetween('-2 months'),
            'is_blocked' => $this->faker->boolean(5), // 5% blocked
            'blocked_at' => null,
            'block_reason' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'external_id' => $this->faker->optional(0.4)->uuid(),
            'source' => $this->faker->randomElement(['website', 'app', 'import', 'api', 'manual']),
            'metadata' => [
                'created_by' => $this->faker->name(),
                'import_batch' => $this->faker->optional(0.3)->uuid(),
            ],
        ];
    }

    /**
     * Contact that is opted in
     */
    public function optedIn(): static
    {
        return $this->state([
            'is_opted_in' => true,
            'opted_in_at' => $this->faker->dateTimeBetween('-1 year'),
            'opted_out_at' => null,
            'opt_out_reason' => null,
        ]);
    }

    /**
     * Contact that is opted out
     */
    public function optedOut(): static
    {
        return $this->state([
            'is_opted_in' => false,
            'opted_in_at' => null,
            'opted_out_at' => $this->faker->dateTimeBetween('-6 months'),
            'opt_out_reason' => $this->faker->randomElement(['spam', 'no_interest', 'too_frequent', 'other']),
        ]);
    }

    /**
     * Verified contact
     */
    public function verified(): static
    {
        return $this->state([
            'is_verified' => true,
            'verified_at' => $this->faker->dateTimeBetween('-3 months'),
            'verification_code' => null,
        ]);
    }

    /**
     * Unverified contact
     */
    public function unverified(): static
    {
        return $this->state([
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    /**
     * Blocked contact
     */
    public function blocked(): static
    {
        return $this->state([
            'is_blocked' => true,
            'blocked_at' => $this->faker->dateTimeBetween('-2 months'),
            'block_reason' => $this->faker->randomElement(['spam', 'abuse', 'invalid', 'fraud']),
        ]);
    }

    /**
     * Active contact (opted in and not blocked)
     */
    public function active(): static
    {
        return $this->state([
            'is_opted_in' => true,
            'opted_in_at' => $this->faker->dateTimeBetween('-1 year'),
            'is_blocked' => false,
            'blocked_at' => null,
            'block_reason' => null,
        ]);
    }

    /**
     * VIP contact
     */
    public function vip(): static
    {
        return $this->state([
            'tags' => ['vip', 'customer'],
            'preferences' => [
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'marketing_enabled' => true,
                'notifications_enabled' => true,
                'priority' => 'high',
            ],
        ]);
    }

    /**
     * Egyptian phone number
     */
    public function egyptian(): static
    {
        return $this->state([
            'phone_number' => '+2010' . $this->faker->numberBetween(10000000, 99999999),
            'country_code' => '20',
            'language' => 'ar',
        ]);
    }

    /**
     * US phone number
     */
    public function us(): static
    {
        return $this->state([
            'phone_number' => '+1' . $this->faker->numberBetween(1000000000, 9999999999),
            'country_code' => '1',
            'language' => 'en',
        ]);
    }
}
