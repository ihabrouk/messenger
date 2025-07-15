<?php

namespace Ihabrouk\Messenger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Messenger Log Model
 *
 * Represents system logs for messaging operations
 */
class Log extends Model
{
    use HasFactory;

    protected $table = 'messenger_logs';

    public $timestamps = true;

    protected $fillable = [
        'log_id',
        'message_id',
        'batch_id',
        'level',
        'event',
        'message',
        'context',
        'provider',
        'provider_message_id',
        'provider_response_code',
        'request_data',
        'response_data',
        'headers',
        'duration_ms',
        'occurred_at',
        'error_code',
        'error_message',
        'stack_trace',
        'webhook_signature',
        'webhook_verified',
        'webhook_event',
        'metadata',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'context' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'duration_ms' => 'decimal:2',
        'occurred_at' => 'datetime',
        'webhook_verified' => 'boolean',
    ];

    // Relationships

    /**
     * Get the related message
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /**
     * Get the related batch
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    // Scopes

    /**
     * Scope by log level
     */
    public function scopeByLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Scope by event
     */
    public function scopeByEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope error logs
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('level', 'error');
    }

    /**
     * Scope warning logs
     */
    public function scopeWarnings(Builder $query): Builder
    {
        return $query->where('level', 'warning');
    }

    /**
     * Scope info logs
     */
    public function scopeInfo(Builder $query): Builder
    {
        return $query->where('level', 'info');
    }

    /**
     * Scope debug logs
     */
    public function scopeDebug(Builder $query): Builder
    {
        return $query->where('level', 'debug');
    }

    /**
     * Scope logs within date range
     */
    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Scope recent logs
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope slow requests (high duration)
     */
    public function scopeSlow(Builder $query, float $thresholdMs = 1000.0): Builder
    {
        return $query->where('duration_ms', '>', $thresholdMs);
    }

    // Accessors

    /**
     * Get the formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (is_null($this->duration_ms)) {
            return 'N/A';
        }

        if ($this->duration_ms < 1000) {
            return number_format($this->duration_ms, 2) . ' ms';
        }

        return number_format($this->duration_ms / 1000, 2) . ' s';
    }

    /**
     * Get the log level display
     */
    public function getLevelDisplayAttribute(): string
    {
        return match($this->level) {
            'error' => '❌ Error',
            'warning' => '⚠️ Warning',
            'info' => 'ℹ️ Info',
            'debug' => '🐛 Debug',
            default => ucfirst($this->level),
        };
    }

    /**
     * Get formatted log entry
     */
    public function getFormattedLogAttribute(): string
    {
        $timestamp = $this->occurred_at->format('Y-m-d H:i:s');
        $level = strtoupper($this->level);

        return "[{$timestamp}] {$level}: {$this->message}";
    }

    /**
     * Check if this is an error log
     */
    public function getIsErrorAttribute(): bool
    {
        return $this->level === 'error';
    }

    /**
     * Check if this is a warning log
     */
    public function getIsWarningAttribute(): bool
    {
        return $this->level === 'warning';
    }

    /**
     * Check if this is a webhook log
     */
    public function getIsWebhookAttribute(): bool
    {
        return !is_null($this->webhook_event);
    }

    /**
     * Check if this is a slow request
     */
    public function getIsSlowAttribute(): bool
    {
        return $this->duration_ms > 1000;
    }

    // Static methods

    /**
     * Log an event
     */
    public static function logEvent(
        string $level,
        string $event,
        string $message,
        array $context = [],
        ?int $messageId = null,
        ?int $batchId = null,
        ?string $provider = null
    ): self {
        return self::create([
            'log_id' => uniqid('log_'),
            'message_id' => $messageId,
            'batch_id' => $batchId,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context,
            'provider' => $provider,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Log an error
     */
    public static function logError(
        string $message,
        array $context = [],
        ?int $messageId = null,
        ?string $provider = null
    ): self {
        return self::logEvent('error', 'error', $message, $context, $messageId, null, $provider);
    }

    /**
     * Log a warning
     */
    public static function logWarning(
        string $message,
        array $context = [],
        ?int $messageId = null,
        ?string $provider = null
    ): self {
        return self::logEvent('warning', 'warning', $message, $context, $messageId, null, $provider);
    }

    /**
     * Log an info message
     */
    public static function logInfo(
        string $message,
        array $context = [],
        ?int $messageId = null,
        ?string $provider = null
    ): self {
        return self::logEvent('info', 'info', $message, $context, $messageId, null, $provider);
    }

    // Factory

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory()
    {
        return \Ihabrouk\Messenger\Database\Factories\LogFactory::new();
    }
}
