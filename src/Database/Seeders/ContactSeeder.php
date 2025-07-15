<?php

namespace Ihabrouk\Messenger\Database\Seeders;

use Illuminate\Database\Seeder;
use Ihabrouk\Messenger\Models\Contact;

/**
 * Contact Seeder
 *
 * Seeds the database with sample contacts for testing and development
 */
class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createTestContacts();
        $this->createDemoContacts();
        $this->createVariousStatusContacts();

        $this->command->info('Contact seeding completed!');
    }

    /**
     * Create test contacts with known data
     */
    private function createTestContacts(): void
    {
        $testContacts = [
            [
                'contact_id' => 'contact_test_001',
                'phone_number' => '+201234567890',
                'formatted_phone' => '+201234567890',
                'country_code' => '20',
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'display_name' => 'Test User',
                'language' => 'en',
                'timezone' => 'Africa/Cairo',
                'is_opted_in' => true,
                'opted_in_at' => now()->subDays(30),
                'opt_in_source' => 'manual',
                'is_verified' => true,
                'verified_at' => now()->subDays(29),
                'tags' => ['test', 'demo'],
                'preferences' => [
                    'newsletter' => true,
                    'promotions' => true,
                    'reminders' => true,
                    'frequency' => 'weekly',
                ],
                'total_messages_sent' => 15,
                'total_messages_delivered' => 14,
                'last_message_at' => now()->subDays(2),
                'last_delivery_at' => now()->subDays(2),
                'source' => 'manual',
                'notes' => 'Test contact for development',
                'metadata' => [
                    'created_for' => 'testing',
                    'department' => 'development',
                ],
            ],
            [
                'contact_id' => 'contact_test_002',
                'phone_number' => '+12025551234',
                'formatted_phone' => '+12025551234',
                'country_code' => '1',
                'email' => 'john.doe@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'display_name' => 'John Doe',
                'language' => 'en',
                'timezone' => 'America/New_York',
                'is_opted_in' => true,
                'opted_in_at' => now()->subDays(60),
                'opt_in_source' => 'website',
                'is_verified' => true,
                'verified_at' => now()->subDays(59),
                'tags' => ['customer', 'vip'],
                'preferences' => [
                    'newsletter' => true,
                    'promotions' => false,
                    'reminders' => true,
                    'alerts' => true,
                    'frequency' => 'daily',
                ],
                'total_messages_sent' => 45,
                'total_messages_delivered' => 43,
                'last_message_at' => now()->subHours(6),
                'last_delivery_at' => now()->subHours(6),
                'source' => 'website',
                'notes' => 'VIP customer - high priority',
                'metadata' => [
                    'customer_segment' => 'vip',
                    'total_orders' => 12,
                    'total_spent' => 2500.00,
                ],
            ],
        ];

        foreach ($testContacts as $contact) {
            Contact::create($contact);
        }
    }

    /**
     * Create demo contacts for demonstration
     */
    private function createDemoContacts(): void
    {
        // Create active customers
        Contact::factory()
            ->count(25)
            ->active()
            ->verified()
            ->customer()
            ->recentlyActive()
            ->create();

        // Create VIP contacts
        Contact::factory()
            ->count(5)
            ->active()
            ->verified()
            ->vip()
            ->highActivity()
            ->create();

        // Create leads
        Contact::factory()
            ->count(20)
            ->active()
            ->unverified()
            ->lead()
            ->lowActivity()
            ->create();

        // Create Arabic-speaking contacts
        Contact::factory()
            ->count(15)
            ->active()
            ->verified()
            ->arabic()
            ->create();

        // Create contacts from different sources
        Contact::factory()
            ->count(10)
            ->active()
            ->fromWebsite()
            ->create();

        Contact::factory()
            ->count(8)
            ->active()
            ->fromApp()
            ->create();

        Contact::factory()
            ->count(12)
            ->active()
            ->fromImport()
            ->create();
    }

    /**
     * Create contacts with various statuses
     */
    private function createVariousStatusContacts(): void
    {
        // Opted out contacts
        Contact::factory()
            ->count(10)
            ->optedOut()
            ->create();

        // Blocked contacts
        Contact::factory()
            ->count(5)
            ->blocked()
            ->create();

        // Unverified contacts
        Contact::factory()
            ->count(15)
            ->optedIn()
            ->unverified()
            ->create();

        // Dormant contacts
        Contact::factory()
            ->count(20)
            ->optedIn()
            ->verified()
            ->dormant()
            ->create();

        // High activity contacts
        Contact::factory()
            ->count(8)
            ->active()
            ->verified()
            ->highActivity()
            ->create();

        // Contacts with different languages
        Contact::factory()
            ->count(5)
            ->active()
            ->language('fr')
            ->create();

        Contact::factory()
            ->count(7)
            ->active()
            ->language('es')
            ->create();

        // Contacts with specific phone number formats
        Contact::factory()
            ->count(20)
            ->active()
            ->usPhone()
            ->create();

        Contact::factory()
            ->count(15)
            ->active()
            ->egyptPhone()
            ->create();

        // Contacts without email
        Contact::factory()
            ->count(25)
            ->active()
            ->withoutEmail()
            ->create();

        // Contacts with specific tags
        Contact::factory()
            ->count(10)
            ->active()
            ->withTags(['newsletter', 'subscriber'])
            ->create();

        Contact::factory()
            ->count(8)
            ->active()
            ->withTags(['employee', 'internal'])
            ->create();

        Contact::factory()
            ->count(12)
            ->active()
            ->withTags(['promotion', 'deal_hunter'])
            ->create();
    }
}
