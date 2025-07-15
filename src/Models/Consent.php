<?php

namespace Ihabrouk\Messenger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Ihabrouk\Messenger\Enums\ConsentStatus;
use Ihabrouk\Messenger\Enums\ConsentType;
use Carbon\Carbon;

class Consent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'messenger_consents';

    protected $fillable = [
        'recipient_phone',
        'type',
        'channel',
        'status',
        'verification_token',
        'granted_at',
        'revoked_at',
        'expires_at',
        'anonymized_at',
        'preferences',
    ];

    protected $casts = [
        'type' => ConsentType::class,
        'status' => ConsentStatus::class,
        'preferences' => 'array',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    // Relationships
    public function messages()
    {
        return $this->hasMany(Message::class, 'recipient_phone', 'recipient_phone');
    }

    // Scopes
    public function scopeOptedIn($query)
    {
        return $query->where('status', ConsentStatus::OPTED_IN);
    }

    public function scopeOptedOut($query)
    {
        return $query->where('status', ConsentStatus::OPTED_OUT);
    }

    public function scopeGranted($query)
    {
        return $query->where('status', ConsentStatus::GRANTED);
    }

    public function scopeRevoked($query)
    {
        return $query->where('status', ConsentStatus::REVOKED);
    }

    public function scopePending($query)
    {
        return $query->where('status', ConsentStatus::PENDING);
    }

    public function scopeForPhone($query, string $phone)
    {
        return $query->where('recipient_phone', $phone);
    }

    public function scopeOfType($query, ConsentType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeExpired($query)
    {
        $retentionDays = config('messenger.consent.retention_days', 2555);

        return $query->where(function($q) use ($retentionDays) {
            // Check explicit expires_at field
            $q->where(function($subQ) {
                $subQ->whereNotNull('expires_at')
                     ->where('expires_at', '<', now());
            })
            // Or check if granted_at is older than retention period
            ->orWhere(function($subQ) use ($retentionDays) {
                $subQ->whereNotNull('granted_at')
                     ->where('granted_at', '<', now()->subDays($retentionDays));
            });
        });
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeForConsentType($query, ConsentType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCanReceiveMarketing($query)
    {
        return $query->where('status', ConsentStatus::OPTED_IN)
                    ->whereIn('type', [ConsentType::MARKETING, ConsentType::ALL]);
    }

    public function scopeCanReceiveTransactional($query)
    {
        return $query->where('status', ConsentStatus::OPTED_IN)
                    ->whereIn('type', [ConsentType::TRANSACTIONAL, ConsentType::ALL]);
    }

    // Methods
    public function optIn(string $source = null, array $preferences = null): void
    {
        $this->update([
            'status' => ConsentStatus::OPTED_IN,
            'opted_in_at' => now(),
            'opted_out_at' => null,
            'source' => $source,
            'preferences' => $preferences ?? $this->preferences,
            'reason' => null,
        ]);
    }

    public function optOut(string $reason = null, string $source = null): void
    {
        $this->update([
            'status' => ConsentStatus::OPTED_OUT,
            'opted_out_at' => now(),
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    public function grant(): bool
    {
        return $this->update([
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
            'revoked_at' => null,
            'verification_token' => null,
        ]);
    }

    public function revoke(string $reason = null): bool
    {
        return $this->update([
            'status' => ConsentStatus::REVOKED,
            'revoked_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === ConsentStatus::GRANTED && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        // Check explicit expires_at
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        // Check if granted_at is older than retention period
        if ($this->granted_at) {
            $retentionDays = config('messenger.consent.retention_days', 2555);
            return $this->granted_at->addDays($retentionDays)->isPast();
        }

        return false;
    }

    public function anonymize(): bool
    {
        return $this->update([
            'recipient_phone' => 'ANON_' . $this->id,
            'preferences' => null,
            'anonymized_at' => now(),
        ]);
    }

    public function updatePreferences(array $preferences): bool
    {
        return $this->update([
            'preferences' => array_merge($this->preferences ?? [], $preferences),
        ]);
    }

    public function canReceive(ConsentType $messageType): bool
    {
        if (!in_array($this->status, [ConsentStatus::OPTED_IN, ConsentStatus::GRANTED])) {
            return false;
        }

        return match ($this->type) {
            ConsentType::ALL => true,
            ConsentType::MARKETING => $messageType === ConsentType::MARKETING,
            ConsentType::TRANSACTIONAL => $messageType === ConsentType::TRANSACTIONAL,
            default => false,
        };
    }

    public function isOptedIn(): bool
    {
        return in_array($this->status, [ConsentStatus::OPTED_IN, ConsentStatus::GRANTED]);
    }

    public function isOptedOut(): bool
    {
        return $this->status === ConsentStatus::OPTED_OUT;
    }

    public function isPending(): bool
    {
        return $this->status === ConsentStatus::PENDING;
    }

    public function getOptInDuration(): ?int
    {
        if (!$this->opted_in_at) {
            return null;
        }

        $endDate = $this->opted_out_at ?? now();
        return $this->opted_in_at->diffInDays($endDate);
    }

    public function getPreference(string $key, $default = null)
    {
        return data_get($this->preferences, $key, $default);
    }

    public function setPreference(string $key, $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->update(['preferences' => $preferences]);
    }

    // Static methods
    public static function findByPhone(string $phoneNumber, string $channel = 'sms'): ?self
    {
        return self::where('recipient_phone', $phoneNumber)
                  ->where('channel', $channel)
                  ->whereIn('status', [ConsentStatus::GRANTED, ConsentStatus::OPTED_IN])
                  ->first();
    }

    public static function createOrUpdateConsent(
        string $phoneNumber,
        string $channel,
        ConsentStatus $status,
        ConsentType $type = ConsentType::ALL,
        string $source = null,
        array $preferences = null
    ): self {
        $consent = self::where('recipient_phone', $phoneNumber)
                      ->where('channel', $channel)
                      ->where('type', $type)
                      ->first();

        $updateData = [
            'status' => $status,
            'type' => $type,
            'source' => $source,
            'preferences' => $preferences,
            'granted_at' => in_array($status, [ConsentStatus::OPTED_IN, ConsentStatus::GRANTED]) ? now() : null,
            'revoked_at' => in_array($status, [ConsentStatus::OPTED_OUT, ConsentStatus::REVOKED]) ? now() : null,
        ];

        // Add verification token for pending status
        if ($status === ConsentStatus::PENDING) {
            $updateData['verification_token'] = \Str::random(64);
        }

        if ($consent) {
            $consent->update($updateData);
        } else {
            $updateData['recipient_phone'] = $phoneNumber;
            $updateData['channel'] = $channel;
            $consent = self::create($updateData);
        }

        return $consent;
    }

    public static function bulkOptOut(array $phoneNumbers, string $channel, string $reason = null): int
    {
        return self::whereIn('recipient_phone', $phoneNumbers)
                  ->where('channel', $channel)
                  ->update([
                      'status' => ConsentStatus::REVOKED,
                      'revoked_at' => now(),
                  ]);
    }

    public static function getOptInRate(string $channel = null, Carbon $from = null, Carbon $to = null): float
    {
        $query = self::query();

        if ($channel) {
            $query->where('channel', $channel);
        }

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        $optedIn = $query->where('status', ConsentStatus::OPTED_IN)->count();
        return ($optedIn / $total) * 100;
    }

    public static function getConsentStats(string $channel = null): array
    {
        $query = self::query();

        if ($channel) {
            $query->where('channel', $channel);
        }

        return [
            'total' => $query->count(),
            'opted_in' => $query->where('status', ConsentStatus::OPTED_IN)->count(),
            'opted_out' => $query->where('status', ConsentStatus::OPTED_OUT)->count(),
            'pending' => $query->where('status', ConsentStatus::PENDING)->count(),
            'marketing' => $query->where('type', ConsentType::MARKETING)->count(),
            'transactional' => $query->where('type', ConsentType::TRANSACTIONAL)->count(),
            'all' => $query->where('type', ConsentType::ALL)->count(),
        ];
    }
}
