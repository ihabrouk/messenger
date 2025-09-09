<?php

namespace Ihabrouk\Messenger\Services;

use Exception;
use Illuminate\Cache\RedisStore;
use Ihabrouk\Messenger\Contracts\TemplateServiceInterface;
use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Enums\MessageProvider;
use Ihabrouk\Messenger\Enums\TemplateCategory;
use Ihabrouk\Messenger\Exceptions\MessengerException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

/**
 * Template Service
 *
 * Handles template rendering, validation, caching, and management
 */
class TemplateService implements TemplateServiceInterface
{
    protected const CACHE_TTL = 3600; // 1 hour
    protected const CACHE_PREFIX = 'messenger:template:';
    protected const VARIABLE_PATTERN = '/\{\{\s*(\w+)\s*\}\}/';

    /**
     * Provider cost rates per SMS (in cents)
     */
    protected array $costRates = [
        'sms_misr' => [
            'local' => 2.5,   // 2.5 cents for local SMS
            'international' => 15.0,
        ],
        'twilio' => [
            'local' => 7.5,   // 7.5 cents for local SMS
            'international' => 12.0,
            'whatsapp' => 5.0,
        ],
        'mocktest' => [
            'local' => 0.0,   // Free for testing
            'international' => 0.0,
        ],
    ];

    /**
     * Render template with variables
     */
    public function render(Template $template, array $variables = []): string
    {
        // Use cached rendered version if available
        $cacheKey = $this->getRenderedCacheKey($template->id, $variables);

        if (Cache::has($cacheKey)) {
            Log::debug('Template render cache hit', [
                'template_id' => $template->id,
                'template_name' => $template->name,
            ]);
            return Cache::get($cacheKey);
        }

        // Validate template before rendering
        $validationErrors = $this->validateTemplate($template);
        if (!empty($validationErrors)) {
            throw new MessengerException(
                'Template validation failed: ' . implode(', ', $validationErrors),
                'TEMPLATE_VALIDATION_FAILED',
                ['template_id' => $template->id, 'errors' => $validationErrors]
            );
        }

        // Validate variables
        $variableErrors = $this->validateVariables($template, $variables);
        if (!empty($variableErrors)) {
            throw new MessengerException(
                'Template variable validation failed: ' . implode(', ', $variableErrors),
                'TEMPLATE_VARIABLE_VALIDATION_FAILED',
                ['template_id' => $template->id, 'errors' => $variableErrors]
            );
        }

        // Merge with sample data for missing variables
        $mergedVariables = $this->mergeWithSampleData($template, $variables);

        // Render the template
        $rendered = $this->performRendering($template->body, $mergedVariables);

        // Cache the rendered result
        Cache::put($cacheKey, $rendered, self::CACHE_TTL);

        // Update usage statistics
        $this->updateUsageStats($template);

        Log::info('Template rendered successfully', [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'characters' => strlen($rendered),
        ]);

        return $rendered;
    }

    /**
     * Render template by name/key
     */
    public function renderByName(string $templateName, array $variables = [], array $options = []): string
    {
        $template = $this->getByName($templateName);

        if (!$template) {
            throw new MessengerException(
                "Template not found: {$templateName}",
                'TEMPLATE_NOT_FOUND',
                ['template_name' => $templateName]
            );
        }

        // Handle A/B testing variant selection
        if (!empty($template->variant_group) && !isset($options['force_template'])) {
            $variantTemplate = $this->selectVariant($template->variant_group);
            if ($variantTemplate) {
                $template = $variantTemplate;
            }
        }

        return $this->render($template, $variables);
    }

    /**
     * Validate template variables
     */
    public function validateVariables(Template $template, array $variables = []): array
    {
        $errors = [];
        $requiredVariables = $this->getRequiredVariables($template);
        $providedVariables = array_keys($variables);

        // Check for missing required variables
        $missingVariables = array_diff($requiredVariables, $providedVariables);
        if (!empty($missingVariables)) {
            $errors[] = 'Missing required variables: ' . implode(', ', $missingVariables);
        }

        // Check for invalid variable values
        foreach ($variables as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $errors[] = "Variable '{$key}' must be a scalar value";
            }

            if (is_string($value) && strlen($value) > 1000) {
                $errors[] = "Variable '{$key}' is too long (max 1000 characters)";
            }
        }

        return $errors;
    }

    /**
     * Validate template content and structure
     */
    public function validateTemplate(Template $template): array
    {
        $errors = [];

        // Check if template is active
        if (!$template->is_active) {
            $errors[] = 'Template is not active';
        }

        // Check if template requires approval and is approved
        if ($template->approval_status === 'pending') {
            $errors[] = 'Template is pending approval';
        }

        if ($template->approval_status === 'rejected') {
            $errors[] = 'Template has been rejected';
        }

        // Check if body exists
        if (empty($template->body)) {
            $errors[] = 'Template body is empty';
        }

        // Check for malformed variables
        if (preg_match_all('/\{\{[^}]*\}\}/', $template->body, $matches)) {
            foreach ($matches[0] as $match) {
                if (!preg_match(self::VARIABLE_PATTERN, $match)) {
                    $errors[] = "Malformed variable: {$match}";
                }
            }
        }

        // Check template length for SMS
        $channels = $template->channels ?? [];
        if (in_array('sms', $channels)) {
            $estimatedLength = $this->estimateRenderedLength($template);
            if ($estimatedLength > 1600) { // Max SMS length
                $errors[] = 'Template too long for SMS (estimated: ' . $estimatedLength . ' characters)';
            }
        }

        return $errors;
    }

    /**
     * Extract variables from template content
     */
    public function extractVariables(string $content): array
    {
        preg_match_all(self::VARIABLE_PATTERN, $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Get required variables for a template
     */
    public function getRequiredVariables(Template $template): array
    {
        $allVariables = $this->extractVariables($template->body);
        $sampleData = $template->sample_data ?? [];

        // Variables are required if they don't have sample data
        return array_diff($allVariables, array_keys($sampleData));
    }

    /**
     * Get cached template
     */
    public function getCachedTemplate(int $templateId): ?Template
    {
        $cacheKey = self::CACHE_PREFIX . $templateId;
        return Cache::get($cacheKey);
    }

    /**
     * Cache template
     */
    public function cacheTemplate(Template $template): void
    {
        $cacheKey = self::CACHE_PREFIX . $template->id;
        Cache::put($cacheKey, $template, self::CACHE_TTL);
    }

    /**
     * Clear template cache
     */
    public function clearTemplateCache(int $templateId): void
    {
        $cacheKey = self::CACHE_PREFIX . $templateId;
        Cache::forget($cacheKey);

        // Also clear rendered cache
        $pattern = "messenger:rendered:{$templateId}:*";
        $this->clearCacheByPattern($pattern);
    }

    /**
     * Clear all template cache
     */
    public function clearAllTemplateCache(): void
    {
        $this->clearCacheByPattern(self::CACHE_PREFIX . '*');
        $this->clearCacheByPattern('messenger:rendered:*');
    }

    /**
     * Get template by key/name
     */
    public function getByName(string $name): ?Template
    {
        // Check cache first
        $cacheKey = self::CACHE_PREFIX . 'name:' . $name;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $template = Template::where('name', $name)
            ->active()
            ->first();

        if ($template) {
            Cache::put($cacheKey, $template, self::CACHE_TTL);
        }

        return $template;
    }

    /**
     * Get templates by category
     */
    public function getByCategory(string $category): Collection
    {
        return Template::where('category', $category)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get templates by channel
     */
    public function getByChannel(string $channel): Collection
    {
        return Template::whereJsonContains('channels', $channel)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Calculate message cost
     */
    public function calculateCost(Template $template, int $recipientCount = 1, array $variables = []): float
    {
        // Get character count
        $characterCount = $this->countCharacters($template, $variables);

        // Calculate SMS segments (160 chars per segment for basic SMS)
        $segments = max(1, ceil($characterCount / 160));

        // Get cost rate based on template settings
        $provider = $template->settings['preferred_provider'] ?? 'sms_misr';
        $messageType = in_array('whatsapp', $template->channels ?? []) ? 'whatsapp' : 'local';

        $ratePerMessage = $this->costRates[$provider][$messageType] ?? $this->costRates['sms_misr']['local'];

        return $segments * $ratePerMessage * $recipientCount / 100; // Convert cents to dollars
    }

    /**
     * Count characters in rendered template
     */
    public function countCharacters(Template $template, array $variables = []): int
    {
        try {
            $rendered = $this->render($template, $variables);
            return mb_strlen($rendered, 'UTF-8');
        } catch (Exception $e) {
            // Fallback to estimated length
            return $this->estimateRenderedLength($template);
        }
    }

    /**
     * Preview template with sample data
     */
    public function preview(Template $template, array $variables = []): array
    {
        // Merge with sample data
        $previewVariables = array_merge($template->sample_data ?? [], $variables);

        try {
            $rendered = $this->render($template, $previewVariables);
            $characterCount = mb_strlen($rendered, 'UTF-8');
            $estimatedCost = $this->calculateCost($template, 1, $previewVariables);

            return [
                'success' => true,
                'rendered' => $rendered,
                'character_count' => $characterCount,
                'estimated_cost' => $estimatedCost,
                'variables_used' => $previewVariables,
                'sms_segments' => max(1, ceil($characterCount / 160)),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'variables_used' => $previewVariables,
            ];
        }
    }

    /**
     * Create template variant for A/B testing
     */
    public function createVariant(Template $template, array $variantData): Template
    {
        $variantGroup = $template->variant_group ?: Str::uuid();

        // If this is the first variant, update the original template
        if (empty($template->variant_group)) {
            $template->update([
                'variant_group' => $variantGroup,
                'variant_weight' => $variantData['original_weight'] ?? 50,
            ]);
        }

        $variant = Template::create(array_merge([
            'name' => $template->name . '_variant_' . time(),
            'display_name' => $variantData['display_name'] ?? $template->display_name . ' (Variant)',
            'description' => $variantData['description'] ?? 'A/B test variant of ' . $template->name,
            'category' => $template->category,
            'type' => $template->type,
            'channels' => $template->channels,
            'language' => $template->language,
            'is_active' => true,
            'is_system' => false,
            'variant_group' => $variantGroup,
            'variant_weight' => $variantData['weight'] ?? 50,
            'parent_template_id' => $template->id,
        ], $variantData));

        Log::info('Template variant created', [
            'original_template_id' => $template->id,
            'variant_template_id' => $variant->id,
            'variant_group' => $variantGroup,
        ]);

        return $variant;
    }

    /**
     * Get best performing variant
     */
    public function getBestVariant(string $variantGroup): ?Template
    {
        // Get all variants in the group with their performance metrics
        $variants = Template::where('variant_group', $variantGroup)
            ->active()
            ->withCount(['messages as total_messages'])
            ->with(['messages' => function ($query) {
                $query->select('template_id')
                    ->selectRaw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_count')
                    ->groupBy('template_id');
            }])
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        // Calculate performance scores
        return $variants->sortByDesc(function ($variant) {
            $totalMessages = $variant->total_messages ?? 0;
            $deliveredMessages = $variant->messages->sum('delivered_count') ?? 0;

            if ($totalMessages === 0) {
                return 0;
            }

            return ($deliveredMessages / $totalMessages) * 100; // Delivery rate percentage
        })->first();
    }

    /**
     * Update template usage statistics
     */
    public function updateUsageStats(Template $template): void
    {
        $template->increment('usage_count');
        $template->update(['last_used_at' => now()]);
    }

    /**
     * Approve template
     */
    public function approve(Template $template, string $approvedBy, string $notes = ''): bool
    {
        $updated = $template->update([
            'approval_status' => 'approved',
            'approval_notes' => $notes,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
            'is_active' => true,
        ]);

        if ($updated) {
            $this->clearTemplateCache($template->id);

            Log::info('Template approved', [
                'template_id' => $template->id,
                'approved_by' => $approvedBy,
                'notes' => $notes,
            ]);
        }

        return $updated;
    }

    /**
     * Reject template
     */
    public function reject(Template $template, string $rejectedBy, string $reason): bool
    {
        $updated = $template->update([
            'approval_status' => 'rejected',
            'approval_notes' => $reason,
            'approved_at' => null,
            'approved_by' => $rejectedBy,
            'is_active' => false,
        ]);

        if ($updated) {
            $this->clearTemplateCache($template->id);

            Log::info('Template rejected', [
                'template_id' => $template->id,
                'rejected_by' => $rejectedBy,
                'reason' => $reason,
            ]);
        }

        return $updated;
    }

    /**
     * Create new template version
     */
    public function createVersion(Template $template, array $changes): Template
    {
        $newVersion = $template->version + 1;

        $versionedTemplate = Template::create(array_merge([
            'name' => $template->name . '_v' . $newVersion,
            'display_name' => $template->display_name,
            'description' => $template->description . ' (Version ' . $newVersion . ')',
            'category' => $template->category,
            'type' => $template->type,
            'channels' => $template->channels,
            'language' => $template->language,
            'version' => $newVersion,
            'parent_template_id' => $template->parent_template_id ?: $template->id,
            'is_active' => false, // New versions start inactive
            'approval_status' => 'pending',
        ], $changes));

        // Deactivate the old version
        $template->update(['is_active' => false]);

        Log::info('Template version created', [
            'original_template_id' => $template->id,
            'new_template_id' => $versionedTemplate->id,
            'version' => $newVersion,
        ]);

        return $versionedTemplate;
    }

    /**
     * Perform the actual template rendering
     */
    protected function performRendering(string $content, array $variables): string
    {
        return preg_replace_callback(self::VARIABLE_PATTERN, function ($matches) use ($variables) {
            $variable = $matches[1];
            return $variables[$variable] ?? "{{ {$variable} }}";
        }, $content);
    }

    /**
     * Merge variables with sample data
     */
    protected function mergeWithSampleData(Template $template, array $variables): array
    {
        $sampleData = $template->sample_data ?? [];
        return array_merge($sampleData, $variables);
    }

    /**
     * Estimate rendered template length
     */
    protected function estimateRenderedLength(Template $template): int
    {
        $content = $template->body;
        $sampleData = $template->sample_data ?? [];

        // Replace variables with sample data for estimation
        foreach ($sampleData as $key => $value) {
            $content = str_replace("{{ {$key} }}", (string) $value, $content);
        }

        // Assume 10 characters for unreplaced variables
        $content = preg_replace(self::VARIABLE_PATTERN, '1234567890', $content);

        return mb_strlen($content, 'UTF-8');
    }

    /**
     * Select variant based on weights
     */
    protected function selectVariant(string $variantGroup): ?Template
    {
        $variants = Template::where('variant_group', $variantGroup)
            ->active()
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $totalWeight = $variants->sum('variant_weight');
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($variants as $variant) {
            $currentWeight += $variant->variant_weight;
            if ($random <= $currentWeight) {
                return $variant;
            }
        }

        return $variants->first();
    }

    /**
     * Get rendered cache key
     */
    protected function getRenderedCacheKey(int $templateId, array $variables): string
    {
        $variableHash = md5(serialize($variables));
        return "messenger:rendered:{$templateId}:{$variableHash}";
    }

    /**
     * Clear cache by pattern
     */
    protected function clearCacheByPattern(string $pattern): void
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or implement a tag-based cache
        if (Cache::getStore() instanceof RedisStore) {
            $redis = Cache::getStore()->getRedis();
            $keys = $redis->keys($pattern);
            if (!empty($keys)) {
                $redis->del($keys);
            }
        }
    }
}
