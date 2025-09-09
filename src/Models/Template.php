<?php

namespace Ihabrouk\Messenger\Models;

use Ihabrouk\Messenger\Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Messenger Template Model
 *
 * Represents message templates for reusable content
 */
class Template extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'messenger_templates';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'type',
        'channels',
        'subject',
        'body',
        'variables',
        'sample_data',
        'language',
        'translations',
        'is_active',
        'is_system',
        'settings',
        'variant_group',
        'variant_weight',
        'usage_count',
        'last_used_at',
        'approval_status',
        'approval_notes',
        'approved_at',
        'approved_by',
        'version',
        'parent_template_id',
    ];

    protected $casts = [
        'channels' => 'array',
        'variables' => 'array',
        'sample_data' => 'array',
        'translations' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'last_used_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Relationships

    /**
     * Get the messages that use this template
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'template_id');
    }

    /**
     * Get the parent template (if this is a variant)
     */
    public function parentTemplate()
    {
        return $this->belongsTo(self::class, 'parent_template_id');
    }

    /**
     * Get the child templates (variants)
     */
    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_template_id');
    }

    // Scopes

    /**
     * Scope active templates
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by language
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Scope approved templates
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Scope system templates
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope custom templates
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope templates by variant group
     */
    public function scopeByVariantGroup(Builder $query, string $group): Builder
    {
        return $query->where('variant_group', $group);
    }

    /**
     * Scope templates available for channel
     */
    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->whereJsonContains('channels', $channel)
                    ->orWhereNull('channels');
    }

    // Accessors and Mutators

    /**
     * Get the status display name
     */
    public function getApprovalStatusDisplayAttribute(): string
    {
        return match($this->approval_status) {
            'draft' => 'Draft',
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->approval_status),
        };
    }

    /**
     * Get the usage statistics
     */
    public function getUsageStatsAttribute(): array
    {
        return [
            'total_usage' => $this->usage_count,
            'recent_usage' => $this->messages()
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'last_used' => $this->last_used_at,
        ];
    }

    /**
     * Check if template supports a channel
     */
    public function supportsChannel(string $channel): bool
    {
        if (is_null($this->channels)) {
            return true; // Support all channels if not specified
        }

        return in_array($channel, $this->channels);
    }

    /**
     * Render the template with provided data
     */
    public function render(array $data = []): string
    {
        $content = $this->body;

        // Replace variables in the format {{variable_name}}
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
            $content = str_replace('{{ ' . $key . ' }}', $value, $content);
        }

        // Use sample data for any remaining variables
        if ($this->sample_data) {
            foreach ($this->sample_data as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
                $content = str_replace('{{ ' . $key . ' }}', $value, $content);
            }
        }

        return $content;
    }

    /**
     * Get all variables used in the template
     */
    public function getUsedVariables(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/', $this->body, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Validate that all required variables are provided
     */
    public function validateData(array $data): array
    {
        $usedVariables = $this->getUsedVariables();
        $missing = [];

        foreach ($usedVariables as $variable) {
            if (!array_key_exists($variable, $data)) {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Create a new variant of this template
     */
    public function createVariant(array $data): self
    {
        $variant = $this->replicate();
        $variant->parent_template_id = $this->id;
        $variant->variant_group = $this->variant_group ?? $this->name;
        $variant->fill($data);
        $variant->save();

        return $variant;
    }

    // Factory

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory()
    {
        return TemplateFactory::new();
    }
}
