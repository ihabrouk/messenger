<?php

namespace Ihabrouk\Messenger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Messenger Batch Model
 *
 * Represents batch/bulk messaging operations
 */
class Batch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'messenger_batches';

    protected $fillable = [
        'batch_id',
        'name',
        'description',
        'provider',
        'type',
        'channel',
        'template_id',
        'subject',
        'body',
        'template_data',
        'status',
        'total_recipients',
        'processed_count',
        'sent_count',
        'failed_count',
        'delivered_count',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'estimated_cost',
        'actual_cost',
        'currency',
        'rate_limit_per_minute',
        'rate_limit_per_hour',
        'respect_timezone',
        'sending_windows',
        'error_message',
        'error_details',
        'retry_count',
        'max_retries',
        'metadata',
        'filters',
        'batchable_type',
        'batchable_id',
        'created_by',
    ];

    protected $casts = [
        'template_data' => 'array',
        'sending_windows' => 'array',
        'error_details' => 'array',
        'metadata' => 'array',
        'filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'estimated_cost' => 'decimal:4',
        'actual_cost' => 'decimal:4',
        'respect_timezone' => 'boolean',
    ];

    // Relationships

    /**
     * Get the template used for this batch
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Get the messages in this batch
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'batch_id', 'batch_id');
    }

    /**
     * Get the logs for this batch
     */
    public function logs(): HasMany
    {
        return $this->hasMany(Log::class, 'batch_id');
    }

    /**
     * Get the parent batchable model (Campaign, Event, etc.)
     */
    public function batchable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    /**
     * Scope by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope pending batches
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope processing batches
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope completed batches
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope failed batches
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope scheduled batches
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now());
    }

    /**
     * Scope batches ready to process
     */
    public function scopeReadyToProcess(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('scheduled_at')
                          ->orWhere('scheduled_at', '<=', now());
                    });
    }

    // Accessors and Mutators

    /**
     * Get the human-readable status
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return round(($this->processed_count / $this->total_recipients) * 100, 2);
    }

    /**
     * Get the success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_count === 0) {
            return 0;
        }

        return round(($this->sent_count / $this->processed_count) * 100, 2);
    }

    /**
     * Get the delivery rate percentage
     */
    public function getDeliveryRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->delivered_count / $this->sent_count) * 100, 2);
    }

    /**
     * Get the formatted estimated cost
     */
    public function getFormattedEstimatedCostAttribute(): string
    {
        if (is_null($this->estimated_cost)) {
            return 'N/A';
        }

        return number_format($this->estimated_cost, 2) . ' ' . $this->currency;
    }

    /**
     * Get the formatted actual cost
     */
    public function getFormattedActualCostAttribute(): string
    {
        if (is_null($this->actual_cost)) {
            return 'N/A';
        }

        return number_format($this->actual_cost, 2) . ' ' . $this->currency;
    }

    /**
     * Get the processing duration
     */
    public function getProcessingDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get the estimated time remaining
     */
    public function getEstimatedTimeRemainingAttribute(): ?int
    {
        if ($this->status !== 'processing' || !$this->started_at || $this->processed_count === 0) {
            return null;
        }

        $elapsedSeconds = $this->started_at->diffInSeconds(now());
        $messagesPerSecond = $this->processed_count / $elapsedSeconds;
        $remainingMessages = $this->total_recipients - $this->processed_count;

        return $messagesPerSecond > 0 ? round($remainingMessages / $messagesPerSecond) : null;
    }

    /**
     * Check if the batch is complete
     */
    public function getIsCompleteAttribute(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    /**
     * Check if the batch is in progress
     */
    public function getIsInProgressAttribute(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the batch can be cancelled
     */
    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    // Methods

    /**
     * Start processing the batch
     */
    public function start(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the batch as completed
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the batch as failed
     */
    public function fail(string $error, array $details = []): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'error_details' => $details,
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel the batch
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Update progress counters
     */
    public function updateProgress(int $sent = 0, int $failed = 0, int $delivered = 0): void
    {
        $this->increment('processed_count', $sent + $failed);

        if ($sent > 0) {
            $this->increment('sent_count', $sent);
        }

        if ($failed > 0) {
            $this->increment('failed_count', $failed);
        }

        if ($delivered > 0) {
            $this->increment('delivered_count', $delivered);
        }

        // Auto-complete if all messages processed
        if ($this->processed_count >= $this->total_recipients && $this->status === 'processing') {
            $this->complete();
        }
    }

    /**
     * Update actual cost
     */
    public function updateCost(float $additionalCost): void
    {
        $this->increment('actual_cost', $additionalCost);
    }

    // Factory

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory()
    {
        return \Ihabrouk\Messenger\Database\Factories\BatchFactory::new();
    }
}
