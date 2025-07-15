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
        Schema::create('messenger_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->unique()->index(); // Unique webhook identifier

            // Provider information
            $table->string('provider')->index(); // smsmisr, twilio, etc.
            $table->string('provider_message_id')->nullable()->index();

            // Webhook details
            $table->string('event')->index(); // delivery_status, incoming_message, etc.
            $table->string('status')->index(); // processed, failed, ignored
            $table->json('payload'); // Full webhook payload
            $table->json('headers')->nullable(); // HTTP headers

            // Security and verification
            $table->string('signature')->nullable(); // Webhook signature
            $table->boolean('verified')->default(false)->index();
            $table->string('verification_method')->nullable(); // hmac, basic_auth, etc.

            // Processing information
            $table->timestamp('received_at')->index(); // When webhook was received
            $table->timestamp('processed_at')->nullable(); // When webhook was processed
            $table->text('processing_error')->nullable(); // Error if processing failed
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();

            // Related records
            $table->foreignId('message_id')->nullable()->constrained('messenger_messages')->nullOnDelete();

            // Request information
            $table->string('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('method')->default('POST');
            $table->string('url')->nullable(); // Webhook endpoint URL

            // Response information
            $table->integer('response_status')->nullable(); // HTTP response status sent back
            $table->json('response_data')->nullable(); // Response sent back to provider

            // Duplicate prevention
            $table->string('idempotency_key')->nullable()->index(); // Prevent duplicate processing

            $table->timestamps();

            // Indexes for performance
            $table->index(['provider', 'event', 'received_at']);
            $table->index(['verified', 'status']);
            $table->index(['received_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_webhooks');
    }
};
