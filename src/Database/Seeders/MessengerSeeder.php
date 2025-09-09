<?php

namespace Ihabrouk\Messenger\Database\Seeders;

use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Models\Contact;
use Ihabrouk\Messenger\Models\Message;
use Illuminate\Database\Seeder;

/**
 * Messenger Database Seeder
 *
 * Main seeder for the Messenger package database
 */
class MessengerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Messenger database seeding...');

        // Seed in order of dependencies
        $this->call([
            TemplateSeeder::class,
            ContactSeeder::class,
            MessageSeeder::class,
        ]);

        $this->command->info('Messenger database seeding completed successfully!');
        $this->printSummary();
    }

    /**
     * Print seeding summary
     */
    private function printSummary(): void
    {
        $templateCount = Template::count();
        $contactCount = Contact::count();
        $messageCount = Message::count();

        $this->command->info('');
        $this->command->info('=== Messenger Seeding Summary ===');
        $this->command->info("Templates created: {$templateCount}");
        $this->command->info("Contacts created: {$contactCount}");
        $this->command->info("Messages created: {$messageCount}");
        $this->command->info('');

        // Show some statistics
        $activeContacts = Contact::active()->count();
        $verifiedContacts = Contact::verified()->count();
        $deliveredMessages = Message::where('status', 'delivered')->count();
        $failedMessages = Message::where('status', 'failed')->count();

        $this->command->info('=== Statistics ===');
        $this->command->info("Active contacts: {$activeContacts}");
        $this->command->info("Verified contacts: {$verifiedContacts}");
        $this->command->info("Delivered messages: {$deliveredMessages}");
        $this->command->info("Failed messages: {$failedMessages}");

        if ($messageCount > 0) {
            $deliveryRate = round(($deliveredMessages / $messageCount) * 100, 1);
            $this->command->info("Overall delivery rate: {$deliveryRate}%");
        }

        $this->command->info('');
    }
}
