<?php

namespace Ihabrouk\Messenger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Messenger Message Model
 *
 * Represents individual messages sent through the messenger system
 */
class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'messenger_messages';

    protected $fillable = [
        'external_id',
        'provider',
        'provider_message_id',
        'type',
        'channel',
        'status',
        'to',
        'from',
        'body',
        'subject',
        'media',
        'template_id',
        'template_data',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'read_at',
        'cost',
        'currency',
        'error_code',
        'error_message',
        'error_details',
        'metadata',
        'context',
        'batch_id',
        'retry_count',
        'last_retry_at',
        'messageable_type',
        'messageable_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'media' => 'array',
        'template_data' => 'array',
        'error_details' => 'array',
        'metadata' => 'array',
        'context' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'read_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'cost' => 'decimal:4',
    ];

    protected $dates = [
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'read_at',
        'last_retry_at',
    ];

    protected $appends = [
        'recipient_phone',
    ];

    // Relationships

    /**
     * Get the template used for this message
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Get the batch this message belongs to
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }

    /**
     * Get the logs for this message
     */
    public function logs(): HasMany
    {
        return $this->hasMany(Log::class, 'message_id');
    }

    /**
     * Get the webhooks for this message
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class, 'message_id');
    }

    /**
     * Get the parent messageable model (User, Order, etc.)
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    /**
     * Scope messages by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope messages by provider
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope messages by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope messages by channel
     */
    public function scopeByChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope sent messages
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->whereNotNull('sent_at');
    }

    /**
     * Scope delivered messages
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->whereNotNull('delivered_at');
    }

    /**
     * Scope failed messages
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope pending messages
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope scheduled messages
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now());
    }

    /**
     * Scope messages ready to send
     */
    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('scheduled_at')
                          ->orWhere('scheduled_at', '<=', now());
                    });
    }

    /**
     * Scope messages by date range
     */
    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope expensive messages (above cost threshold)
     */
    public function scopeExpensive(Builder $query, float $threshold = 1.0): Builder
    {
        return $query->where('cost', '>', $threshold);
    }

    // Accessors and Mutators

    /**
     * Get the human-readable status
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'queued' => 'Queued',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'read' => 'Read',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the formatted cost with currency
     */
    public function getFormattedCostAttribute(): string
    {
        if (is_null($this->cost)) {
            return 'N/A';
        }

        return number_format($this->cost, 4) . ' ' . $this->currency;
    }

    /**
     * Check if the message was successfully sent
     */
    public function getIsSentAttribute(): bool
    {
        return !is_null($this->sent_at);
    }

    /**
     * Check if the message was delivered
     */
    public function getIsDeliveredAttribute(): bool
    {
        return !is_null($this->delivered_at);
    }

    /**
     * Check if the message failed
     */
    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the message is pending
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get delivery duration in seconds
     */
    public function getDeliveryDurationAttribute(): ?int
    {
        if (!$this->sent_at || !$this->delivered_at) {
            return null;
        }

        return $this->delivered_at->diffInSeconds($this->sent_at);
    }

    /**
     * Get the recipient name or phone number
     */
    public function getRecipientDisplayAttribute(): string
    {
        // Try to get name from messageable relationship
        if ($this->messageable && method_exists($this->messageable, 'getDisplayName')) {
            return $this->messageable->getDisplayName();
        }

        // Fallback to phone/email
        return $this->to;
    }

    /**
     * Accessor for backward compatibility - maps recipient_phone to to
     */
    public function getRecipientPhoneAttribute(): ?string
    {
        return $this->to;
    }

    /**
     * Mutator for backward compatibility - maps recipient_phone to to
     */
    public function setRecipientPhoneAttribute($value): void
    {
        $this->attributes['to'] = $value;
    }

    /**
     * Convert message to SendMessageData for sending
     */
    public function toSendData(): \Ihabrouk\Messenger\Data\SendMessageData
    {
        return new \Ihabrouk\Messenger\Data\SendMessageData(
            $this->to,
            $this->body,
            $this->provider,
            $this->channel,
            $this->template_id,
            $this->variables ?? [],
            $this->priority ?? \Ihabrouk\Messenger\Enums\MessagePriority::NORMAL,
            $this->scheduled_at,
            $this->messageable_type,
            $this->messageable_id
        );
    }

    // Factory

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory()
    {
        return \Ihabrouk\Messenger\Database\Factories\MessageFactory::new();
    }
}
