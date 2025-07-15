<?php

namespace Ihabrouk\Messenger\Database\Seeders;

use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Enums\TemplateCategory;
use Illuminate\Database\Seeder;

/**
 * Template Seeder
 *
 * Creates pre-built message templates for common use cases
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->createOtpTemplates();
        $this->createWelcomeTemplates();
        $this->createVerificationTemplates();
        $this->createNotificationTemplates();
        $this->createMarketingTemplates();
        $this->createTransactionalTemplates();
        $this->createEmergencyTemplates();

        $this->command->info('Created pre-built message templates');
    }

    /**
     * Create OTP templates
     */
    protected function createOtpTemplates(): void
    {
        Template::create([
            'name' => 'otp_basic',
            'display_name' => 'Basic OTP Code',
            'description' => 'Simple OTP verification code template',
            'category' => 'otp',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => 'Your verification code is: {{ otp_code }}. Valid for {{ expiry_minutes }} minutes.',
            'variables' => ['otp_code', 'expiry_minutes'],
            'sample_data' => [
                'otp_code' => '123456',
                'expiry_minutes' => '10',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);

        Template::create([
            'name' => 'otp_branded',
            'display_name' => 'Branded OTP Code',
            'description' => 'OTP template with company branding',
            'category' => 'otp',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => '{{ company_name }}: Your verification code is {{ otp_code }}. This code expires in {{ expiry_minutes }} minutes. Do not share this code with anyone.',
            'variables' => ['company_name', 'otp_code', 'expiry_minutes'],
            'sample_data' => [
                'company_name' => 'MyCompany',
                'otp_code' => '654321',
                'expiry_minutes' => '15',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);

        Template::create([
            'name' => 'otp_arabic',
            'display_name' => 'Arabic OTP Code',
            'description' => 'OTP template in Arabic',
            'category' => 'otp',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => 'رمز التحقق الخاص بك هو: {{ otp_code }}. صالح لمدة {{ expiry_minutes }} دقائق.',
            'variables' => ['otp_code', 'expiry_minutes'],
            'sample_data' => [
                'otp_code' => '789012',
                'expiry_minutes' => '10',
            ],
            'language' => 'ar',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);
    }

    /**
     * Create welcome templates
     */
    protected function createWelcomeTemplates(): void
    {
        Template::create([
            'name' => 'welcome_new_member',
            'display_name' => 'Welcome New Member',
            'description' => 'Welcome message for new members',
            'category' => 'welcome',
            'type' => 'marketing',
            'channels' => ['sms', 'whatsapp'],
            'subject' => 'Welcome to {{ company_name }}!',
            'body' => 'Welcome to {{ company_name }}, {{ first_name }}! Your membership ID is {{ member_id }}. We\'re excited to have you join us!',
            'variables' => ['company_name', 'first_name', 'member_id'],
            'sample_data' => [
                'company_name' => 'Elite Club',
                'first_name' => 'John',
                'member_id' => 'M12345',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);

        Template::create([
            'name' => 'welcome_guest',
            'display_name' => 'Welcome Guest',
            'description' => 'Welcome message for guests',
            'category' => 'welcome',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => 'Welcome {{ guest_name }}! You\'re registered for {{ event_name }} on {{ event_date }}. Enjoy your visit!',
            'variables' => ['guest_name', 'event_name', 'event_date'],
            'sample_data' => [
                'guest_name' => 'Sarah Smith',
                'event_name' => 'Annual Gala',
                'event_date' => 'Dec 25, 2025',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);
    }

    /**
     * Create verification templates
     */
    protected function createVerificationTemplates(): void
    {
        Template::create([
            'name' => 'phone_verification',
            'display_name' => 'Phone Number Verification',
            'description' => 'Phone number verification template',
            'category' => 'verification',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => 'Please verify your phone number by clicking: {{ verification_link }} or enter code: {{ verification_code }}',
            'variables' => ['verification_link', 'verification_code'],
            'sample_data' => [
                'verification_link' => 'https://example.com/verify?token=abc123',
                'verification_code' => '987654',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);

        Template::create([
            'name' => 'email_verification',
            'display_name' => 'Email Verification',
            'description' => 'Email verification confirmation',
            'category' => 'verification',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => 'Your email {{ email_address }} has been verified successfully. Welcome to {{ app_name }}!',
            'variables' => ['email_address', 'app_name'],
            'sample_data' => [
                'email_address' => 'user@example.com',
                'app_name' => 'MyApp',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);
    }

    /**
     * Create notification templates
     */
    protected function createNotificationTemplates(): void
    {
        Template::create([
            'name' => 'payment_received',
            'display_name' => 'Payment Received',
            'description' => 'Payment confirmation notification',
            'category' => 'notification',
            'type' => 'transactional',
            'channels' => ['sms', 'whatsapp'],
            'subject' => 'Payment Received',
            'body' => 'Payment of {{ currency }}{{ amount }} received for {{ description }}. Transaction ID: {{ transaction_id }}. Thank you!',
            'variables' => ['currency', 'amount', 'description', 'transaction_id'],
            'sample_data' => [
                'currency' => '$',
                'amount' => '50.00',
                'description' => 'Monthly Membership',
                'transaction_id' => 'TXN123456',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);

        Template::create([
            'name' => 'appointment_reminder',
            'display_name' => 'Appointment Reminder',
            'description' => 'Reminder for upcoming appointments',
            'category' => 'notification',
            'type' => 'transactional',
            'channels' => ['sms', 'whatsapp'],
            'subject' => 'Appointment Reminder',
            'body' => 'Reminder: You have an appointment with {{ provider_name }} on {{ appointment_date }} at {{ appointment_time }}. Location: {{ location }}',
            'variables' => ['provider_name', 'appointment_date', 'appointment_time', 'location'],
            'sample_data' => [
                'provider_name' => 'Dr. Smith',
                'appointment_date' => 'July 20, 2025',
                'appointment_time' => '2:00 PM',
                'location' => 'Main Office',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);
    }

    /**
     * Create marketing templates
     */
    protected function createMarketingTemplates(): void
    {
        Template::create([
            'name' => 'special_offer',
            'display_name' => 'Special Offer',
            'description' => 'Marketing template for special offers',
            'category' => 'marketing',
            'type' => 'marketing',
            'channels' => ['sms', 'whatsapp'],
            'subject' => 'Special Offer Just for You!',
            'body' => '🎉 Special offer for {{ customer_name }}! Get {{ discount_percentage }}% off {{ product_name }}. Use code: {{ promo_code }}. Valid until {{ expiry_date }}.',
            'variables' => ['customer_name', 'discount_percentage', 'product_name', 'promo_code', 'expiry_date'],
            'sample_data' => [
                'customer_name' => 'John Doe',
                'discount_percentage' => '20',
                'product_name' => 'Premium Membership',
                'promo_code' => 'SAVE20',
                'expiry_date' => 'July 31, 2025',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);

        Template::create([
            'name' => 'event_invitation',
            'display_name' => 'Event Invitation',
            'description' => 'Invitation to events',
            'category' => 'marketing',
            'type' => 'marketing',
            'channels' => ['sms', 'whatsapp'],
            'subject' => 'You\'re Invited!',
            'body' => 'You\'re invited to {{ event_name }} on {{ event_date }} at {{ event_location }}. RSVP by {{ rsvp_deadline }}: {{ rsvp_link }}',
            'variables' => ['event_name', 'event_date', 'event_location', 'rsvp_deadline', 'rsvp_link'],
            'sample_data' => [
                'event_name' => 'Summer BBQ',
                'event_date' => 'August 15, 2025',
                'event_location' => 'Club Garden',
                'rsvp_deadline' => 'August 10',
                'rsvp_link' => 'https://example.com/rsvp/123',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);
    }

    /**
     * Create transactional templates
     */
    protected function createTransactionalTemplates(): void
    {
        Template::create([
            'name' => 'password_reset',
            'display_name' => 'Password Reset',
            'description' => 'Password reset instructions',
            'category' => 'transactional',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => 'Reset your password by clicking: {{ reset_link }} or use code: {{ reset_code }}. This link expires in {{ expiry_minutes }} minutes.',
            'variables' => ['reset_link', 'reset_code', 'expiry_minutes'],
            'sample_data' => [
                'reset_link' => 'https://example.com/reset?token=xyz789',
                'reset_code' => '456789',
                'expiry_minutes' => '30',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);

        Template::create([
            'name' => 'account_locked',
            'display_name' => 'Account Locked',
            'description' => 'Account security notification',
            'category' => 'transactional',
            'type' => 'transactional',
            'channels' => ['sms'],
            'subject' => null,
            'body' => 'Your account has been temporarily locked due to suspicious activity. Please contact support at {{ support_phone }} or unlock at {{ unlock_link }}',
            'variables' => ['support_phone', 'unlock_link'],
            'sample_data' => [
                'support_phone' => '+1-800-123-4567',
                'unlock_link' => 'https://example.com/unlock',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);
    }

    /**
     * Create emergency templates
     */
    protected function createEmergencyTemplates(): void
    {
        Template::create([
            'name' => 'emergency_alert',
            'display_name' => 'Emergency Alert',
            'description' => 'Emergency notification template',
            'category' => 'emergency',
            'type' => 'emergency',
            'channels' => ['sms', 'whatsapp'],
            'subject' => 'EMERGENCY ALERT',
            'body' => '🚨 EMERGENCY ALERT: {{ alert_message }}. Please follow instructions: {{ instructions }}. For help call: {{ emergency_contact }}',
            'variables' => ['alert_message', 'instructions', 'emergency_contact'],
            'sample_data' => [
                'alert_message' => 'Facility evacuation required',
                'instructions' => 'Exit via nearest emergency exit',
                'emergency_contact' => '911',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
            'settings' => [
                'priority' => 'high',
                'preferred_provider' => 'sms_misr',
            ],
        ]);

        Template::create([
            'name' => 'system_maintenance',
            'display_name' => 'System Maintenance',
            'description' => 'System maintenance notification',
            'category' => 'emergency',
            'type' => 'transactional',
            'channels' => ['sms', 'whatsapp'],
            'subject' => 'System Maintenance',
            'body' => 'Scheduled maintenance: {{ system_name }} will be unavailable from {{ start_time }} to {{ end_time }} on {{ maintenance_date }}. We apologize for any inconvenience.',
            'variables' => ['system_name', 'start_time', 'end_time', 'maintenance_date'],
            'sample_data' => [
                'system_name' => 'Member Portal',
                'start_time' => '2:00 AM',
                'end_time' => '4:00 AM',
                'maintenance_date' => 'Sunday, July 20, 2025',
            ],
            'language' => 'en',
            'is_active' => true,
            'is_system' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'system',
            'version' => 1,
        ]);
    }
}
