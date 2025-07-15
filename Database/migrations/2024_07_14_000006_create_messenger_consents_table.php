<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_consents', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index();
            $table->string('channel')->default('sms'); // sms, whatsapp, email
            $table->enum('status', ['opted_in', 'opted_out', 'pending'])->default('pending');
            $table->enum('consent_type', ['marketing', 'transactional', 'all'])->default('all');
            $table->json('preferences')->nullable(); // Additional preferences
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->string('source')->nullable(); // web, api, sms_reply, etc.
            $table->text('reason')->nullable(); // Reason for opt-out
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['phone_number', 'channel']);
            $table->index(['status', 'consent_type']);
            $table->index(['opted_in_at', 'opted_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_consents');
    }
};
