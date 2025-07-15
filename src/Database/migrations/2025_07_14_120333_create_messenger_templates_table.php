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
        Schema::create('messenger_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Template identifier
            $table->string('display_name'); // Human-readable name
            $table->text('description')->nullable();

            // Template categorization
            $table->string('category')->index(); // otp, welcome, notification, marketing, etc.
            $table->string('type')->index(); // sms, whatsapp, email
            $table->json('channels')->nullable(); // Available channels for this template

            // Template content
            $table->text('subject')->nullable(); // For email or rich messages
            $table->text('body'); // Template body with variables
            $table->json('variables')->nullable(); // Available variables with descriptions
            $table->json('sample_data')->nullable(); // Sample data for preview

            // Localization
            $table->string('language', 5)->default('en')->index(); // Language code
            $table->json('translations')->nullable(); // Other language versions

            // Template settings
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false); // System templates can't be deleted
            $table->json('settings')->nullable(); // Template-specific settings

            // A/B Testing
            $table->string('variant_group')->nullable()->index(); // Group variants together
            $table->integer('variant_weight')->default(100); // Weight for A/B testing

            // Usage tracking
            $table->integer('usage_count')->default(0); // Times used
            $table->timestamp('last_used_at')->nullable();

            // Approval workflow
            $table->string('approval_status')->default('draft')->index(); // draft, pending, approved, rejected
            $table->text('approval_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['category', 'type']);
            $table->index(['is_active', 'approval_status']);
            $table->index(['variant_group', 'variant_weight']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_templates');
    }
};
