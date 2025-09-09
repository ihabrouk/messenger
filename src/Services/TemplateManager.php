<?php

namespace Ihabrouk\Messenger\Services;

use InvalidArgumentException;
use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Services\TemplateService;
use Illuminate\Support\Collection;

/**
 * Template Manager
 *
 * Higher-level template management operations
 */
class TemplateManager
{
    public function __construct(
        protected TemplateService $templateService
    ) {}

    /**
     * Get templates for a specific use case
     */
    public function getTemplatesForUseCase(string $useCase, array $options = []): Collection
    {
        $templates = collect();

        switch ($useCase) {
            case 'member_registration':
                $templates = $this->templateService->getByCategory('welcome')
                    ->merge($this->templateService->getByCategory('verification'));
                break;

            case 'payment_processing':
                $templates = $this->templateService->getByCategory('transactional')
                    ->filter(fn($t) => str_contains($t->name, 'payment'));
                break;

            case 'event_management':
                $templates = $this->templateService->getByCategory('marketing')
                    ->filter(fn($t) => str_contains($t->name, 'event'))
                    ->merge($this->templateService->getByCategory('notification')
                        ->filter(fn($t) => str_contains($t->name, 'appointment')));
                break;

            case 'security_alerts':
                $templates = $this->templateService->getByCategory('emergency')
                    ->merge($this->templateService->getByCategory('transactional')
                        ->filter(fn($t) => str_contains($t->name, 'password') || str_contains($t->name, 'account')));
                break;

            case 'marketing_campaigns':
                $templates = $this->templateService->getByCategory('marketing');
                break;

            default:
                $templates = Template::active()->get();
        }

        // Apply filters
        if (isset($options['channel'])) {
            $templates = $templates->filter(fn($t) => in_array($options['channel'], $t->channels ?? []));
        }

        if (isset($options['language'])) {
            $templates = $templates->filter(fn($t) => $t->language === $options['language']);
        }

        return $templates->sortBy('display_name');
    }

    /**
     * Create template from common patterns
     */
    public function createFromPattern(string $pattern, array $data): Template
    {
        $patterns = $this->getTemplatePatterns();

        if (!isset($patterns[$pattern])) {
            throw new InvalidArgumentException("Unknown template pattern: {$pattern}");
        }

        $patternData = $patterns[$pattern];

        return Template::create(array_merge($patternData, $data, [
            'name' => $data['name'] ?? $pattern . '_' . time(),
            'is_system' => false,
            'approval_status' => 'pending',
            'version' => 1,
        ]));
    }

    /**
     * Duplicate template with modifications
     */
    public function duplicateTemplate(Template $template, array $modifications = []): Template
    {
        $newTemplate = $template->replicate();

        // Update name to avoid conflicts
        $newTemplate->name = ($modifications['name'] ?? $template->name) . '_copy_' . time();
        $newTemplate->display_name = ($modifications['display_name'] ?? $template->display_name) . ' (Copy)';

        // Reset approval status
        $newTemplate->approval_status = 'pending';
        $newTemplate->approved_at = null;
        $newTemplate->approved_by = null;
        $newTemplate->is_active = false;
        $newTemplate->usage_count = 0;
        $newTemplate->last_used_at = null;

        // Apply modifications
        foreach ($modifications as $key => $value) {
            if ($newTemplate->isFillable($key)) {
                $newTemplate->{$key} = $value;
            }
        }

        $newTemplate->save();

        return $newTemplate;
    }

    /**
     * Bulk approve templates
     */
    public function bulkApprove(array $templateIds, string $approvedBy, string $notes = ''): array
    {
        $results = [];

        foreach ($templateIds as $templateId) {
            $template = Template::find($templateId);
            if ($template) {
                $success = $this->templateService->approve($template, $approvedBy, $notes);
                $results[$templateId] = $success;
            }
        }

        return $results;
    }

    /**
     * Get template statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_templates' => Template::count(),
            'active_templates' => Template::active()->count(),
            'pending_approval' => Template::where('approval_status', 'pending')->count(),
            'by_category' => Template::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'by_channel' => $this->getChannelStatistics(),
            'most_used' => Template::orderBy('usage_count', 'desc')
                ->limit(5)
                ->select('name', 'display_name', 'usage_count')
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Find templates needing updates
     */
    public function findOutdatedTemplates(): Collection
    {
        return Template::where('last_used_at', '<', now()->subMonths(6))
            ->orWhere('created_at', '<', now()->subYear())
            ->where('usage_count', '<', 10)
            ->get();
    }

    /**
     * Get template suggestions for content
     */
    public function suggestTemplates(string $content, array $context = []): Collection
    {
        $suggestions = collect();

        // Simple keyword matching
        $keywords = str_word_count($content, 1);

        foreach (['otp', 'verify', 'code'] as $otpKeyword) {
            if (in_array(strtolower($otpKeyword), array_map('strtolower', $keywords))) {
                $suggestions = $suggestions->merge($this->templateService->getByCategory('otp'));
                break;
            }
        }

        foreach (['welcome', 'hello', 'join'] as $welcomeKeyword) {
            if (in_array(strtolower($welcomeKeyword), array_map('strtolower', $keywords))) {
                $suggestions = $suggestions->merge($this->templateService->getByCategory('welcome'));
                break;
            }
        }

        foreach (['payment', 'receipt', 'transaction'] as $paymentKeyword) {
            if (in_array(strtolower($paymentKeyword), array_map('strtolower', $keywords))) {
                $suggestions = $suggestions->merge(
                    Template::where('body', 'like', '%payment%')
                        ->orWhere('body', 'like', '%transaction%')
                        ->active()
                        ->get()
                );
                break;
            }
        }

        return $suggestions->unique('id')->sortBy('display_name');
    }

    /**
     * Get template patterns for quick creation
     */
    protected function getTemplatePatterns(): array
    {
        return [
            'simple_otp' => [
                'display_name' => 'Simple OTP',
                'description' => 'Basic OTP code template',
                'category' => 'otp',
                'type' => 'transactional',
                'channels' => ['sms'],
                'body' => 'Your code: {{ otp_code }}',
                'variables' => ['otp_code'],
                'sample_data' => ['otp_code' => '123456'],
                'language' => 'en',
            ],
            'welcome_message' => [
                'display_name' => 'Welcome Message',
                'description' => 'Basic welcome template',
                'category' => 'welcome',
                'type' => 'marketing',
                'channels' => ['sms', 'whatsapp'],
                'body' => 'Welcome {{ name }}! We\'re glad to have you.',
                'variables' => ['name'],
                'sample_data' => ['name' => 'John'],
                'language' => 'en',
            ],
            'reminder_notification' => [
                'display_name' => 'Reminder Notification',
                'description' => 'Basic reminder template',
                'category' => 'notification',
                'type' => 'transactional',
                'channels' => ['sms', 'whatsapp'],
                'body' => 'Reminder: {{ event }} on {{ date }} at {{ time }}',
                'variables' => ['event', 'date', 'time'],
                'sample_data' => ['event' => 'Meeting', 'date' => 'Tomorrow', 'time' => '2 PM'],
                'language' => 'en',
            ],
        ];
    }

    /**
     * Get channel statistics
     */
    protected function getChannelStatistics(): array
    {
        $templates = Template::all();
        $stats = [];

        foreach ($templates as $template) {
            $channels = $template->channels ?? [];
            foreach ($channels as $channel) {
                $stats[$channel] = ($stats[$channel] ?? 0) + 1;
            }
        }

        return $stats;
    }
}
