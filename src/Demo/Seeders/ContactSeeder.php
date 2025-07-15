<?php

namespace Ihabrouk\Messenger\Demo\Seeders;

use Ihabrouk\Messenger\Demo\Models\Contact;
use Illuminate\Database\Seeder;

/**
 * Demo Contact Seeder - FOR TESTING/DEMO PURPOSES ONLY
 *
 * ⚠️ IMPORTANT: This seeder is for demonstration and testing purposes only.
 * In production, you would seed your existing User/Member models instead.
 */
class ContactSeeder extends Seeder
{
    public function run(): void
    {
        // Create a variety of demo contacts for testing

        // Active VIP customers
        Contact::factory()
            ->count(5)
            ->active()
            ->vip()
            ->verified()
            ->create();

        // Regular active customers
        Contact::factory()
            ->count(15)
            ->active()
            ->verified()
            ->create();

        // Egyptian customers
        Contact::factory()
            ->count(10)
            ->egyptian()
            ->active()
            ->create();

        // US customers
        Contact::factory()
            ->count(8)
            ->us()
            ->active()
            ->create();

        // Opted out contacts
        Contact::factory()
            ->count(3)
            ->optedOut()
            ->create();

        // Unverified contacts
        Contact::factory()
            ->count(5)
            ->unverified()
            ->optedIn()
            ->create();

        // Blocked contacts
        Contact::factory()
            ->count(2)
            ->blocked()
            ->create();

        // Create a few specific test contacts
        Contact::factory()->create([
            'contact_id' => 'test_contact_1',
            'phone_number' => '+201234567890',
            'first_name' => 'Ahmed',
            'last_name' => 'Hassan',
            'email' => 'ahmed.hassan@example.com',
            'language' => 'ar',
            'is_opted_in' => true,
            'is_verified' => true,
            'tags' => ['test', 'egyptian', 'customer'],
        ]);

        Contact::factory()->create([
            'contact_id' => 'test_contact_2',
            'phone_number' => '+11234567890',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'language' => 'en',
            'is_opted_in' => true,
            'is_verified' => true,
            'tags' => ['test', 'us', 'vip'],
        ]);

        $this->command->info('Created demo contacts for testing purposes.');
        $this->command->warn('Remember: These are demo contacts only. In production, use your existing User/Member models.');
    }
}
