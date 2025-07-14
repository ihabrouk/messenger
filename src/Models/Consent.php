<?php

namespace App\Messenger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Messenger\Enums\ConsentStatus;
use App\Messenger\Enums\ConsentType;
use Carbon\Carbon;

class Consent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'messenger_consents';

    protected $fillable = [
        'phone_number',
        'channel',
        'status',
        'consent_type',
        'preferences',
        'opted_in_at',
        'opted_out_at',
        'source',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'preferences' => 'array',
        'metadata' => 'array',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'status' => ConsentStatus::class,
        'consent_type' => ConsentType::class,
    ];

    // Relationships
    public function messages()
    {
        return $this->hasMany(Message::class, 'recipient_phone', 'phone_number');
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

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeForConsentType($query, ConsentType $type)
    {
        return $query->where('consent_type', $type);
    }

    public function scopeCanReceiveMarketing($query)
    {
        return $query->where('status', ConsentStatus::OPTED_IN)
                    ->whereIn('consent_type', [ConsentType::MARKETING, ConsentType::ALL]);
    }

    public function scopeCanReceiveTransactional($query)
    {
        return $query->where('status', ConsentStatus::OPTED_IN)
                    ->whereIn('consent_type', [ConsentType::TRANSACTIONAL, ConsentType::ALL]);
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

    public function canReceive(ConsentType $messageType): bool
    {
        if ($this->status !== ConsentStatus::OPTED_IN) {
            return false;
        }

        return match ($this->consent_type) {
            ConsentType::ALL => true,
            ConsentType::MARKETING => $messageType === ConsentType::MARKETING,
            ConsentType::TRANSACTIONAL => $messageType === ConsentType::TRANSACTIONAL,
            default => false,
        };
    }

    public function isOptedIn(): bool
    {
        return $this->status === ConsentStatus::OPTED_IN;
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

    public function updatePreferences(array $preferences): void
    {
        $this->update([
            'preferences' => array_merge($this->preferences ?? [], $preferences),
        ]);
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
        return self::where('phone_number', $phoneNumber)
                  ->where('channel', $channel)
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
        $consent = self::findByPhone($phoneNumber, $channel);

        if ($consent) {
            $consent->update([
                'status' => $status,
                'consent_type' => $type,
                'source' => $source,
                'preferences' => $preferences,
                'opted_in_at' => $status === ConsentStatus::OPTED_IN ? now() : $consent->opted_in_at,
                'opted_out_at' => $status === ConsentStatus::OPTED_OUT ? now() : null,
            ]);
        } else {
            $consent = self::create([
                'phone_number' => $phoneNumber,
                'channel' => $channel,
                'status' => $status,
                'consent_type' => $type,
                'source' => $source,
                'preferences' => $preferences,
                'opted_in_at' => $status === ConsentStatus::OPTED_IN ? now() : null,
                'opted_out_at' => $status === ConsentStatus::OPTED_OUT ? now() : null,
            ]);
        }

        return $consent;
    }

    public static function bulkOptOut(array $phoneNumbers, string $channel, string $reason = null): int
    {
        return self::whereIn('phone_number', $phoneNumbers)
                  ->where('channel', $channel)
                  ->update([
                      'status' => ConsentStatus::OPTED_OUT,
                      'opted_out_at' => now(),
                      'reason' => $reason,
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
            'marketing' => $query->where('consent_type', ConsentType::MARKETING)->count(),
            'transactional' => $query->where('consent_type', ConsentType::TRANSACTIONAL)->count(),
            'all' => $query->where('consent_type', ConsentType::ALL)->count(),
        ];
    }
}
