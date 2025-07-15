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
        Schema::create('messenger_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_id')->unique()->index(); // Unique log identifier

            // Related records
            $table->foreignId('message_id')->nullable()->constrained('messenger_messages')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('messenger_batches')->cascadeOnDelete();

            // Log details
            $table->string('level')->index(); // info, warning, error, debug
            $table->string('event')->index(); // message_sent, delivery_failed, webhook_received, etc.
            $table->text('message'); // Log message
            $table->json('context')->nullable(); // Additional context data

            // Provider information
            $table->string('provider')->nullable()->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('provider_response_code')->nullable();

            // Request/Response data
            $table->json('request_data')->nullable(); // API request data
            $table->json('response_data')->nullable(); // API response data
            $table->json('headers')->nullable(); // HTTP headers

            // Timing information
            $table->decimal('duration_ms', 8, 2)->nullable(); // Request duration in milliseconds
            $table->timestamp('occurred_at')->index(); // When the event occurred

            // Error details
            $table->string('error_code')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->text('stack_trace')->nullable(); // For exceptions

            // Webhook information
            $table->string('webhook_signature')->nullable();
            $table->boolean('webhook_verified')->nullable();
            $table->string('webhook_event')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->string('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();

            // Indexes for performance and log querying
            $table->index(['level', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['occurred_at', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_logs');
    }
};
