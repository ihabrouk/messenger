<?php

namespace Ihabrouk\Messenger\Models;

use Exception;
use Carbon\Carbon;
use Ihabrouk\Messenger\Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Messenger Webhook Model
 *
 * Represents webhook events from messaging providers
 */
class Webhook extends Model
{
    use HasFactory;

    protected $table = 'messenger_webhooks';

    public $timestamps = true;

    protected $fillable = [
        'webhook_id',
        'message_id',
        'provider',
        'provider_message_id',
        'event_type',
        'status',
        'delivery_status',
        'delivered_at',
        'read_at',
        'error_code',
        'error_message',
        'raw_payload',
        'processed_payload',
        'headers',
        'signature',
        'is_verified',
        'verification_attempts',
        'processed',
        'processed_at',
        'failure_reason',
        'retry_count',
        'next_retry_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'processed_payload' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'is_verified' => 'boolean',
        'processed' => 'boolean',
        'verification_attempts' => 'integer',
        'retry_count' => 'integer',
    ];

    // Relationships

    /**
     * Get the related message
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    // Scopes

    /**
     * Scope by provider
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope by event type
     */
    public function scopeByEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope by delivery status
     */
    public function scopeByDeliveryStatus(Builder $query, string $deliveryStatus): Builder
    {
        return $query->where('delivery_status', $deliveryStatus);
    }

    /**
     * Scope verified webhooks
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope unverified webhooks
     */
    public function scopeUnverified(Builder $query): Builder
    {
        return $query->where('is_verified', false);
    }

    /**
     * Scope processed webhooks
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('processed', true);
    }

    /**
     * Scope unprocessed webhooks
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('processed', false);
    }

    /**
     * Scope pending retry
     */
    public function scopePendingRetry(Builder $query): Builder
    {
        return $query->where('processed', false)
                    ->where('next_retry_at', '<=', now());
    }

    /**
     * Scope delivery events
     */
    public function scopeDeliveryEvents(Builder $query): Builder
    {
        return $query->whereIn('event_type', ['delivered', 'delivery_confirmed']);
    }

    /**
     * Scope read events
     */
    public function scopeReadEvents(Builder $query): Builder
    {
        return $query->where('event_type', 'read');
    }

    /**
     * Scope failed events
     */
    public function scopeFailedEvents(Builder $query): Builder
    {
        return $query->whereIn('event_type', ['failed', 'rejected', 'undelivered']);
    }

    /**
     * Scope recent webhooks
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Accessors

    /**
     * Get the event type display
     */
    public function getEventTypeDisplayAttribute(): string
    {
        return match($this->event_type) {
            'delivered' => '✅ Delivered',
            'delivery_confirmed' => '✅ Delivery Confirmed',
            'read' => '👁️ Read',
            'failed' => '❌ Failed',
            'rejected' => '🚫 Rejected',
            'undelivered' => '⚠️ Undelivered',
            'clicked' => '🔗 Clicked',
            'replied' => '💬 Replied',
            default => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }

    /**
     * Get the status display
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'received' => '📥 Received',
            'processing' => '⚙️ Processing',
            'processed' => '✅ Processed',
            'failed' => '❌ Failed',
            'invalid' => '🚫 Invalid',
            default => ucfirst($this->status),
        };
    }

    /**
     * Check if webhook is delivery related
     */
    public function getIsDeliveryEventAttribute(): bool
    {
        return in_array($this->event_type, ['delivered', 'delivery_confirmed']);
    }

    /**
     * Check if webhook is read event
     */
    public function getIsReadEventAttribute(): bool
    {
        return $this->event_type === 'read';
    }

    /**
     * Check if webhook is failure event
     */
    public function getIsFailureEventAttribute(): bool
    {
        return in_array($this->event_type, ['failed', 'rejected', 'undelivered']);
    }

    /**
     * Check if webhook can be retried
     */
    public function getCanRetryAttribute(): bool
    {
        return !$this->processed &&
               $this->retry_count < 5 &&
               (!$this->next_retry_at || $this->next_retry_at <= now());
    }

    /**
     * Get formatted payload for display
     */
    public function getFormattedPayloadAttribute(): string
    {
        return json_encode($this->processed_payload ?: $this->raw_payload, JSON_PRETTY_PRINT);
    }

    /**
     * Get time since received
     */
    public function getTimeSinceReceivedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // Methods

    /**
     * Mark webhook as processed
     */
    public function markAsProcessed(): self
    {
        $this->update([
            'processed' => true,
            'processed_at' => now(),
            'status' => 'processed',
        ]);

        return $this;
    }

    /**
     * Mark webhook as failed
     */
    public function markAsFailed(string $reason): self
    {
        $this->update([
            'processed' => false,
            'status' => 'failed',
            'failure_reason' => $reason,
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => now()->addMinutes(pow(2, $this->retry_count)), // Exponential backoff
        ]);

        return $this;
    }

    /**
     * Update message delivery status
     */
    public function updateMessageStatus(): void
    {
        if (!$this->message) {
            return;
        }

        $updates = [];

        if ($this->is_delivery_event) {
            $updates['status'] = 'delivered';
            $updates['delivered_at'] = $this->delivered_at ?: now();
        }

        if ($this->is_read_event) {
            $updates['read_at'] = $this->read_at ?: now();
        }

        if ($this->is_failure_event) {
            $updates['status'] = 'failed';
            $updates['error_code'] = $this->error_code;
            $updates['error_message'] = $this->error_message;
        }

        if (!empty($updates)) {
            $this->message->update($updates);
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifySignature(string $secret): bool
    {
        if (!$this->signature) {
            return false;
        }

        // This is a generic implementation - each provider will have its own
        $expectedSignature = hash_hmac('sha256', json_encode($this->raw_payload), $secret);

        $isValid = hash_equals($expectedSignature, $this->signature);

        $this->update([
            'is_verified' => $isValid,
            'verification_attempts' => $this->verification_attempts + 1,
        ]);

        return $isValid;
    }

    /**
     * Process the webhook payload
     */
    public function process(): void
    {
        try {
            // Extract relevant data from raw payload based on provider
            $processedData = $this->processPayloadByProvider();

            $this->update(['processed_payload' => $processedData]);

            // Update message status if applicable
            $this->updateMessageStatus();

            $this->markAsProcessed();

        } catch (Exception $e) {
            $this->markAsFailed($e->getMessage());
        }
    }

    /**
     * Process payload based on provider
     */
    protected function processPayloadByProvider(): array
    {
        return match($this->provider) {
            'sms_misr' => $this->processSMSMisrPayload(),
            'twilio' => $this->processTwilioPayload(),
            default => $this->raw_payload,
        };
    }

    /**
     * Process SMS Misr webhook payload
     */
    protected function processSMSMisrPayload(): array
    {
        $payload = $this->raw_payload;

        return [
            'message_id' => $payload['messageId'] ?? null,
            'status' => $payload['status'] ?? null,
            'delivered_at' => isset($payload['deliveredAt']) ?
                Carbon::parse($payload['deliveredAt']) : null,
        ];
    }

    /**
     * Process Twilio webhook payload
     */
    protected function processTwilioPayload(): array
    {
        $payload = $this->raw_payload;

        return [
            'message_sid' => $payload['MessageSid'] ?? null,
            'status' => $payload['MessageStatus'] ?? null,
            'error_code' => $payload['ErrorCode'] ?? null,
            'error_message' => $payload['ErrorMessage'] ?? null,
        ];
    }

    // Static methods

    /**
     * Create webhook from incoming request
     */
    public static function createFromRequest(
        string $provider,
        array $payload,
        array $headers = [],
        ?string $signature = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'webhook_id' => uniqid('webhook_'),
            'provider' => $provider,
            'raw_payload' => $payload,
            'headers' => $headers,
            'signature' => $signature,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => 'received',
            'processed' => false,
            'is_verified' => false,
            'verification_attempts' => 0,
            'retry_count' => 0,
        ]);
    }

    // Factory

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory()
    {
        return WebhookFactory::new();
    }
}
