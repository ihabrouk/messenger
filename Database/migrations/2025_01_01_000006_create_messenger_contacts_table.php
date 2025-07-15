<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messenger_contacts', function (Blueprint $table) {
            $table->id();

            // Contact identification
            $table->string('phone')->nullable()->index(); // Primary phone number
            $table->string('email')->nullable()->index(); // Primary email address
            $table->string('whatsapp')->nullable()->index(); // WhatsApp number (might differ from phone)

            // Contact details
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable(); // Computed or provided full name
            $table->string('display_name')->nullable(); // Preferred display name

            // Preferences and settings
            $table->json('channel_preferences')->nullable(); // Preferred channels per message type
            $table->json('language_preferences')->nullable(); // Language preferences
            $table->boolean('opt_in_sms')->default(false)->index();
            $table->boolean('opt_in_whatsapp')->default(false)->index();
            $table->boolean('opt_in_email')->default(false)->index();
            $table->boolean('opt_in_marketing')->default(false)->index();

            // Opt-in/out tracking
            $table->timestamp('sms_opted_in_at')->nullable();
            $table->timestamp('sms_opted_out_at')->nullable();
            $table->timestamp('whatsapp_opted_in_at')->nullable();
            $table->timestamp('whatsapp_opted_out_at')->nullable();
            $table->timestamp('email_opted_in_at')->nullable();
            $table->timestamp('email_opted_out_at')->nullable();
            $table->timestamp('marketing_opted_in_at')->nullable();
            $table->timestamp('marketing_opted_out_at')->nullable();

            // Contact metadata
            $table->string('timezone')->nullable(); // Contact's timezone
            $table->string('country_code', 2)->nullable(); // ISO country code
            $table->string('carrier')->nullable(); // Mobile carrier
            $table->json('custom_fields')->nullable(); // Additional custom data

            // Status and validation
            $table->string('status')->default('active')->index(); // active, inactive, blocked, invalid
            $table->boolean('phone_verified')->default(false);
            $table->boolean('email_verified')->default(false);
            $table->boolean('whatsapp_verified')->default(false);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('whatsapp_verified_at')->nullable();

            // Delivery tracking
            $table->timestamp('last_sms_sent_at')->nullable();
            $table->timestamp('last_whatsapp_sent_at')->nullable();
            $table->timestamp('last_email_sent_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            // Blocking and bounces
            $table->boolean('sms_blocked')->default(false);
            $table->boolean('whatsapp_blocked')->default(false);
            $table->boolean('email_blocked')->default(false);
            $table->integer('sms_bounce_count')->default(0);
            $table->integer('whatsapp_bounce_count')->default(0);
            $table->integer('email_bounce_count')->default(0);

            // Integration with application models
            $table->morphs('contactable'); // Polymorphic relation to User, Customer, etc.

            // Tags and categorization
            $table->json('tags')->nullable(); // Contact tags
            $table->json('groups')->nullable(); // Contact groups

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['opt_in_sms', 'sms_blocked']);
            $table->index(['opt_in_whatsapp', 'whatsapp_blocked']);
            $table->index(['opt_in_email', 'email_blocked']);

            // Unique constraints to prevent duplicates
            $table->unique(['phone', 'deleted_at']);
            $table->unique(['email', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_contacts');
    }
};
