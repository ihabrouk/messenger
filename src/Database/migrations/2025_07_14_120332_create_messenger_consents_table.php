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
        if (!Schema::hasTable('messenger_consents')) {
            Schema::create('messenger_consents', function (Blueprint $table) {
                $table->id();
                $table->string('recipient_phone');
                $table->string('type'); // marketing, notifications, reminders, alerts, transactional
                $table->string('status')->default('pending'); // pending, granted, revoked, expired
                $table->string('verification_token')->nullable();
                $table->timestamp('granted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('anonymized_at')->nullable();
                $table->json('preferences')->nullable();
                $table->timestamps();

                // Indexes for performance
                $table->index(['recipient_phone', 'type']);
                $table->index(['status']);
                $table->index(['granted_at']);
                $table->index(['verification_token']);

                // Unique constraint for phone + type combination
                $table->unique(['recipient_phone', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_consents');
    }
};
