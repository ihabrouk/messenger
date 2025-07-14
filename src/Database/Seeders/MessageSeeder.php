<?php

namespace App\Messenger\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Messenger\Models\Message;
use App\Messenger\Models\Template;
use App\Messenger\Models\Contact;
use App\Messenger\Models\Batch;

/**
 * Message Seeder
 *
 * Seeds the database with sample messages for testing and development
 */
class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing contacts and templates
        $contacts = Contact::active()->limit(50)->get();
        $templates = Template::active()->get();

        if ($contacts->isEmpty()) {
            $this->command->warn('No contacts found. Run ContactSeeder first.');
            return;
        }

        $this->createTestMessages($contacts, $templates);
        $this->createRecentMessages($contacts, $templates);
        $this->createHistoricalMessages($contacts, $templates);
        $this->createFailedMessages($contacts);
        $this->createScheduledMessages($contacts, $templates);

        $this->command->info('Message seeding completed!');
    }

    /**
     * Create test messages with known data
     */
    private function createTestMessages($contacts, $templates): void
    {
        $testContact = $contacts->first();

        if (!$testContact) {
            return;
        }

        $testMessages = [
            [
                'message_id' => 'msg_test_001',
                'recipient_phone' => $testContact->phone_number,
                'recipient_name' => $testContact->full_name,
                'content' => 'Welcome to our service! This is a test message.',
                'provider' => 'mock_test',
                'provider_message_id' => 'mock_msg_001',
                'status' => 'delivered',
                'priority' => 'normal',
                'sent_at' => now()->subHours(2),
                'delivered_at' => now()->subHours(2)->addMinutes(1),
                'cost' => 0.00,
                'currency' => 'USD',
                'direction' => 'outbound',
                'message_type' => 'sms',
                'encoding' => 'gsm7',
                'parts_count' => 1,
                'character_count' => 50,
                'tags' => ['test', 'welcome'],
                'metadata' => [
                    'test_message' => true,
                    'created_by' => 'seeder',
                ],
            ],
            [
                'message_id' => 'msg_test_002',
                'recipient_phone' => $testContact->phone_number,
                'recipient_name' => $testContact->full_name,
                'content' => 'Your order #12345 has been confirmed and will be delivered tomorrow.',
                'provider' => 'sms_misr',
                'provider_message_id' => 'sms_misr_001',
                'status' => 'delivered',
                'priority' => 'high',
                'sent_at' => now()->subDays(1),
                'delivered_at' => now()->subDays(1)->addMinutes(2),
                'read_at' => now()->subDays(1)->addMinutes(5),
                'cost' => 0.05,
                'currency' => 'USD',
                'direction' => 'outbound',
                'message_type' => 'sms',
                'encoding' => 'gsm7',
                'parts_count' => 1,
                'character_count' => 68,
                'tags' => ['order', 'confirmation'],
                'metadata' => [
                    'order_id' => '12345',
                    'test_message' => true,
                ],
            ],
        ];

        foreach ($testMessages as $message) {
            Message::create($message);
        }
    }

    /**
     * Create recent messages (last 24 hours)
     */
    private function createRecentMessages($contacts, $templates): void
    {
        // Recent delivered messages
        Message::factory()
            ->count(15)
            ->delivered()
            ->recent()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // Recent sent messages (not yet delivered)
        Message::factory()
            ->count(8)
            ->sent()
            ->recent()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // Recent read messages
        Message::factory()
            ->count(10)
            ->read()
            ->recent()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // Recent messages with templates
        if ($templates->isNotEmpty()) {
            Message::factory()
                ->count(12)
                ->delivered()
                ->recent()
                ->create([
                    'template_id' => $templates->random()->id,
                    'recipient_phone' => $contacts->random()->phone_number,
                    'recipient_name' => $contacts->random()->full_name,
                ]);
        }
    }

    /**
     * Create historical messages (older data)
     */
    private function createHistoricalMessages($contacts, $templates): void
    {
        // Messages from the last 30 days
        Message::factory()
            ->count(100)
            ->delivered()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
                'sent_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
            ]);

        // Messages with different providers
        Message::factory()
            ->count(20)
            ->delivered()
            ->provider('sms_misr')
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        Message::factory()
            ->count(15)
            ->delivered()
            ->provider('twilio')
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        Message::factory()
            ->count(25)
            ->delivered()
            ->provider('mock_test')
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // High priority messages
        Message::factory()
            ->count(10)
            ->delivered()
            ->highPriority()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // Multi-part messages
        Message::factory()
            ->count(8)
            ->delivered()
            ->multiPart()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // Expensive messages
        Message::factory()
            ->count(5)
            ->delivered()
            ->expensive()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);
    }

    /**
     * Create failed messages
     */
    private function createFailedMessages($contacts): void
    {
        // Recent failed messages
        Message::factory()
            ->count(12)
            ->failed()
            ->recent()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // Historical failed messages
        Message::factory()
            ->count(20)
            ->failed()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
                'sent_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
            ]);

        // Failed messages with different providers
        Message::factory()
            ->count(8)
            ->failed()
            ->provider('sms_misr')
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        Message::factory()
            ->count(6)
            ->failed()
            ->provider('twilio')
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);
    }

    /**
     * Create scheduled messages
     */
    private function createScheduledMessages($contacts, $templates): void
    {
        // Pending messages scheduled for future
        Message::factory()
            ->count(15)
            ->scheduled()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);

        // Scheduled messages with templates
        if ($templates->isNotEmpty()) {
            Message::factory()
                ->count(8)
                ->scheduled()
                ->create([
                    'template_id' => $templates->random()->id,
                    'recipient_phone' => $contacts->random()->phone_number,
                    'recipient_name' => $contacts->random()->full_name,
                ]);
        }

        // High priority scheduled messages
        Message::factory()
            ->count(5)
            ->scheduled()
            ->highPriority()
            ->create([
                'recipient_phone' => $contacts->random()->phone_number,
                'recipient_name' => $contacts->random()->full_name,
            ]);
    }
}
