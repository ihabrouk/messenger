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
        Schema::create('messenger_messages', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index(); // For tracking with external systems
            $table->string('provider')->index(); // smsmisr, twilio, etc.
            $table->string('provider_message_id')->nullable()->index(); // Provider's message ID
            $table->string('type')->index(); // sms, whatsapp, otp, email
            $table->string('channel')->index(); // sms, whatsapp
            $table->string('status')->default('pending')->index(); // pending, sent, delivered, failed, etc.

            // Recipient information
            $table->string('to'); // Phone number or email
            $table->string('from')->nullable(); // Sender ID or number

            // Message content
            $table->text('body'); // Message content
            $table->string('subject')->nullable(); // For email or rich messages
            $table->json('media')->nullable(); // Media attachments (URLs, file paths)

            // Template information
            $table->foreignId('template_id')->nullable()->constrained('messenger_templates')->nullOnDelete();
            $table->json('template_data')->nullable(); // Variables used in template

            // Delivery information
            $table->timestamp('scheduled_at')->nullable()->index(); // For scheduled messages
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();

            // Cost and billing
            $table->decimal('cost', 8, 4)->nullable(); // Cost of sending
            $table->string('currency', 3)->default('USD');

            // Error handling
            $table->string('error_code')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable(); // Additional error context

            // Metadata and context
            $table->json('metadata')->nullable(); // Additional provider-specific data
            $table->json('context')->nullable(); // Application context (user_id, order_id, etc.)

            // Tracking and analytics
            $table->string('batch_id')->nullable()->index(); // For bulk messages
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();

            // Relationships with application models
            $table->nullableMorphs('messageable'); // Polymorphic relation to User, Order, etc.

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['provider', 'status']);
            $table->index(['batch_id', 'status']);
            $table->index(['scheduled_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_messages');
    }
};
