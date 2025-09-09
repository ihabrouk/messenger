<?php

namespace Ihabrouk\Messenger\Demo\Models;

use Ihabrouk\Messenger\Demo\Factories\ContactFactory;
use Ihabrouk\Messenger\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Demo Contact Model - FOR TESTING/DEMO PURPOSES ONLY
 *
 * ⚠️ IMPORTANT: This model is for demonstration and testing purposes only.
 *
 * In production, you should use your existing User, Member, Customer, or similar models.
 * The Messenger package works with phone numbers as identifiers, so it's compatible
 * with any existing user model that has a phone number field.
 *
 * This Contact model provides:
 * - Testing capabilities for the package
 * - Example implementation for new projects
 * - Demonstration of messaging preferences and opt-in/out functionality
 *
 * To integrate with your existing user model:
 * 1. Add messaging-related fields to your User/Member model if needed
 * 2. Use the phone number as the identifier when sending messages
 * 3. Implement opt-in/out functionality in your existing model
 * 4. Use the polymorphic messageable relationship in Message model
 */
class Contact extends Model
{
    use HasFactory;

    protected $table = 'messenger_contacts';

    public $timestamps = true;

    protected $fillable = [
        'contact_id',
        'phone_number',
        'formatted_phone',
        'country_code',
        'email',
        'first_name',
        'last_name',
        'display_name',
        'language',
        'timezone',
        'is_opted_in',
        'opted_in_at',
        'opted_out_at',
        'opt_in_source',
        'opt_out_reason',
        'is_verified',
        'verified_at',
        'verification_code',
        'verification_attempts',
        'last_verification_at',
        'tags',
        'preferences',
        'last_message_at',
        'total_messages_sent',
        'total_messages_delivered',
        'last_delivery_at',
        'is_blocked',
        'blocked_at',
        'block_reason',
        'notes',
        'external_id',
        'source',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'preferences' => 'array',
        'metadata' => 'array',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'verified_at' => 'datetime',
        'last_verification_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_delivery_at' => 'datetime',
        'blocked_at' => 'datetime',
        'is_opted_in' => 'boolean',
        'is_verified' => 'boolean',
        'is_blocked' => 'boolean',
        'total_messages_sent' => 'integer',
        'total_messages_delivered' => 'integer',
        'verification_attempts' => 'integer',
    ];

    // Relationships

    /**
     * Get all messages sent to this contact
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_phone', 'phone_number');
    }

    /**
     * Get recent messages
     */
    public function recentMessages(): HasMany
    {
        return $this->messages()->orderBy('created_at', 'desc')->limit(10);
    }

    /**
     * Get delivered messages
     */
    public function deliveredMessages(): HasMany
    {
        return $this->messages()->where('status', 'delivered');
    }

    /**
     * Get failed messages
     */
    public function failedMessages(): HasMany
    {
        return $this->messages()->where('status', 'failed');
    }

    // Scopes

    /**
     * Scope opted in contacts
     */
    public function scopeOptedIn(Builder $query): Builder
    {
        return $query->where('is_opted_in', true);
    }

    /**
     * Scope opted out contacts
     */
    public function scopeOptedOut(Builder $query): Builder
    {
        return $query->where('is_opted_in', false);
    }

    /**
     * Scope verified contacts
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope unverified contacts
     */
    public function scopeUnverified(Builder $query): Builder
    {
        return $query->where('is_verified', false);
    }

    /**
     * Scope blocked contacts
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('is_blocked', true);
    }

    /**
     * Scope active contacts (opted in and not blocked)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_opted_in', true)
                    ->where('is_blocked', false);
    }

    /**
     * Scope by country code
     */
    public function scopeByCountryCode(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope by language
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Scope by timezone
     */
    public function scopeByTimezone(Builder $query, string $timezone): Builder
    {
        return $query->where('timezone', $timezone);
    }

    /**
     * Scope by tags
     */
    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Scope by any of the provided tags
     */
    public function scopeWithAnyTag(Builder $query, array $tags): Builder
    {
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereJsonContains('tags', $tag);
            }
        });
    }

    /**
     * Scope by all provided tags
     */
    public function scopeWithAllTags(Builder $query, array $tags): Builder
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
        return $query;
    }

    /**
     * Scope recently active contacts
     */
    public function scopeRecentlyActive(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_message_at', '>=', now()->subDays($days));
    }

    /**
     * Scope by source
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    // Accessors

    /**
     * Get the full name
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }

        return $this->first_name ?: $this->last_name ?: $this->display_name ?: 'Unknown';
    }

    /**
     * Get the display name or fallback
     */
    public function getDisplayNameOrFallbackAttribute(): string
    {
        return $this->display_name ?: $this->full_name;
    }

    /**
     * Get opt-in status display
     */
    public function getOptInStatusDisplayAttribute(): string
    {
        if ($this->is_blocked) {
            return '🚫 Blocked';
        }

        return $this->is_opted_in ? '✅ Opted In' : '❌ Opted Out';
    }

    /**
     * Get verification status display
     */
    public function getVerificationStatusDisplayAttribute(): string
    {
        return $this->is_verified ? '✅ Verified' : '⚠️ Unverified';
    }

    /**
     * Get delivery rate
     */
    public function getDeliveryRateAttribute(): float
    {
        if ($this->total_messages_sent === 0) {
            return 0;
        }

        return ($this->total_messages_delivered / $this->total_messages_sent) * 100;
    }

    /**
     * Get formatted delivery rate
     */
    public function getFormattedDeliveryRateAttribute(): string
    {
        return number_format($this->delivery_rate, 1) . '%';
    }

    /**
     * Check if contact can receive messages
     */
    public function getCanReceiveMessagesAttribute(): bool
    {
        return $this->is_opted_in && !$this->is_blocked;
    }

    /**
     * Get time since last message
     */
    public function getTimeSinceLastMessageAttribute(): ?string
    {
        return $this->last_message_at?->diffForHumans();
    }

    /**
     * Get formatted phone number
     */
    public function getFormattedPhoneNumberAttribute(): string
    {
        return $this->formatted_phone ?: $this->phone_number;
    }

    // Mutators

    /**
     * Set the phone number and format it
     */
    public function setPhoneNumberAttribute(string $value): void
    {
        $this->attributes['phone_number'] = $value;

        // Basic phone formatting - in real app you'd use a library like libphonenumber
        $cleaned = preg_replace('/[^0-9+]/', '', $value);
        $this->attributes['formatted_phone'] = $cleaned;

        // Extract country code if it starts with +
        if (str_starts_with($cleaned, '+')) {
            $this->attributes['country_code'] = substr($cleaned, 1, 2);
        }
    }

    // Methods

    /**
     * Opt in the contact
     */
    public function optIn(string $source = null): self
    {
        $this->update([
            'is_opted_in' => true,
            'opted_in_at' => now(),
            'opted_out_at' => null,
            'opt_in_source' => $source,
            'opt_out_reason' => null,
        ]);

        return $this;
    }

    /**
     * Opt out the contact
     */
    public function optOut(string $reason = null): self
    {
        $this->update([
            'is_opted_in' => false,
            'opted_out_at' => now(),
            'opt_out_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Block the contact
     */
    public function block(string $reason = null): self
    {
        $this->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Unblock the contact
     */
    public function unblock(): self
    {
        $this->update([
            'is_blocked' => false,
            'blocked_at' => null,
            'block_reason' => null,
        ]);

        return $this;
    }

    /**
     * Add a tag to the contact
     */
    public function addTag(string $tag): self
    {
        $tags = $this->tags ?: [];

        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }

        return $this;
    }

    /**
     * Remove a tag from the contact
     */
    public function removeTag(string $tag): self
    {
        $tags = $this->tags ?: [];

        $tags = array_filter($tags, fn($t) => $t !== $tag);

        $this->update(['tags' => array_values($tags)]);

        return $this;
    }

    /**
     * Set multiple tags
     */
    public function setTags(array $tags): self
    {
        $this->update(['tags' => array_values(array_unique($tags))]);

        return $this;
    }

    /**
     * Update message statistics
     */
    public function updateMessageStats(): self
    {
        $this->update([
            'total_messages_sent' => $this->messages()->count(),
            'total_messages_delivered' => $this->deliveredMessages()->count(),
            'last_message_at' => $this->messages()->latest()->value('created_at'),
            'last_delivery_at' => $this->deliveredMessages()->latest()->value('delivered_at'),
        ]);

        return $this;
    }

    /**
     * Generate verification code
     */
    public function generateVerificationCode(): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'verification_code' => $code,
            'verification_attempts' => 0,
            'last_verification_at' => now(),
        ]);

        return $code;
    }

    /**
     * Verify the contact with code
     */
    public function verify(string $code): bool
    {
        $this->increment('verification_attempts');

        if ($this->verification_code === $code &&
            $this->last_verification_at >= now()->subMinutes(15)) {

            $this->update([
                'is_verified' => true,
                'verified_at' => now(),
                'verification_code' => null,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Set a preference
     */
    public function setPreference(string $key, $value): self
    {
        $preferences = $this->preferences ?: [];
        $preferences[$key] = $value;

        $this->update(['preferences' => $preferences]);

        return $this;
    }

    /**
     * Get a preference
     */
    public function getPreference(string $key, $default = null)
    {
        return data_get($this->preferences, $key, $default);
    }

    // Static methods

    /**
     * Find or create contact by phone number
     */
    public static function findOrCreateByPhone(
        string $phoneNumber,
        array $attributes = []
    ): self {
        $contact = self::where('phone_number', $phoneNumber)->first();

        if (!$contact) {
            $contact = self::create(array_merge([
                'contact_id' => uniqid('contact_'),
                'phone_number' => $phoneNumber,
                'is_opted_in' => true,
                'opted_in_at' => now(),
                'source' => 'auto_created',
            ], $attributes));
        }

        return $contact;
    }

    /**
     * Bulk opt-in contacts
     */
    public static function bulkOptIn(array $phoneNumbers, string $source = null): int
    {
        return self::whereIn('phone_number', $phoneNumbers)
                  ->update([
                      'is_opted_in' => true,
                      'opted_in_at' => now(),
                      'opt_in_source' => $source,
                      'opted_out_at' => null,
                      'opt_out_reason' => null,
                  ]);
    }

    /**
     * Bulk opt-out contacts
     */
    public static function bulkOptOut(array $phoneNumbers, string $reason = null): int
    {
        return self::whereIn('phone_number', $phoneNumbers)
                  ->update([
                      'is_opted_in' => false,
                      'opted_out_at' => now(),
                      'opt_out_reason' => $reason,
                  ]);
    }

    // Factory

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory()
    {
        return ContactFactory::new();
    }
}
