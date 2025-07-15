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
        Schema::create('messenger_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique()->index(); // Unique batch identifier
            $table->string('name')->nullable(); // Human-readable batch name
            $table->text('description')->nullable();

            // Batch configuration
            $table->string('provider')->index(); // Primary provider for this batch
            $table->string('type')->index(); // sms, whatsapp, email
            $table->string('channel')->index(); // sms, whatsapp
            $table->foreignId('template_id')->nullable()->constrained('messenger_templates')->nullOnDelete();

            // Batch content
            $table->text('subject')->nullable(); // For email batches
            $table->text('body')->nullable(); // If not using template
            $table->json('template_data')->nullable(); // Global template variables

            // Batch status and progress
            $table->string('status')->default('pending')->index(); // pending, processing, completed, failed, cancelled
            $table->integer('total_recipients')->default(0);
            $table->integer('processed_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('delivered_count')->default(0);

            // Scheduling
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Cost tracking
            $table->decimal('estimated_cost', 10, 4)->nullable(); // Estimated total cost
            $table->decimal('actual_cost', 10, 4)->nullable(); // Actual total cost
            $table->string('currency', 3)->default('USD');

            // Rate limiting and sending options
            $table->integer('rate_limit_per_minute')->nullable(); // Messages per minute
            $table->integer('rate_limit_per_hour')->nullable(); // Messages per hour
            $table->boolean('respect_timezone')->default(false); // Send at recipient's timezone
            $table->json('sending_windows')->nullable(); // Time windows for sending

            // Error handling
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);

            // Metadata and context
            $table->json('metadata')->nullable(); // Additional batch data
            $table->json('filters')->nullable(); // Recipient filters used

            // Relationships
            $table->morphs('batchable'); // Polymorphic relation to Campaign, Event, etc.
            $table->unsignedBigInteger('created_by')->nullable(); // User who created the batch

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'scheduled_at']);
            $table->index(['provider', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_batches');
    }
};
