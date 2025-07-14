<?php

namespace App\Messenger\Commands;

use App\Messenger\Services\TemplateService;
use App\Messenger\Services\TemplateManager;
use App\Messenger\Models\Template;
use Illuminate\Console\Command;

/**
 * Manage Templates Command
 *
 * Comprehensive template management operations
 */
class ManageTemplatesCommand extends Command
{
    protected $signature = 'messenger:templates
                           {action : Action to perform (list, create, approve, reject, duplicate, stats)}
                           {template? : Template name or ID (for specific actions)}
                           {--category= : Filter by category}
                           {--status= : Filter by status (active, pending, rejected)}
                           {--provider= : Filter by provider capability}
                           {--interactive : Interactive mode}';

    protected $description = 'Manage message templates';

    public function handle(TemplateService $templateService, TemplateManager $templateManager): int
    {
        $action = $this->argument('action');

        return match($action) {
            'list' => $this->listTemplates(),
            'create' => $this->createTemplate($templateManager),
            'approve' => $this->approveTemplate($templateService),
            'reject' => $this->rejectTemplate($templateService),
            'duplicate' => $this->duplicateTemplate($templateManager),
            'stats' => $this->showStatistics($templateManager),
            'cleanup' => $this->cleanupTemplates($templateManager),
            default => $this->showHelp(),
        };
    }

    protected function listTemplates(): int
    {
        $query = Template::query();

        // Apply filters
        if ($category = $this->option('category')) {
            $query->where('category', $category);
        }

        if ($status = $this->option('status')) {
            match($status) {
                'active' => $query->where('is_active', true),
                'pending' => $query->where('approval_status', 'pending'),
                'rejected' => $query->where('approval_status', 'rejected'),
            };
        }

        $templates = $query->orderBy('category')->orderBy('name')->get();

        if ($templates->isEmpty()) {
            $this->info('No templates found matching criteria');
            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Category', 'Status', 'Channels', 'Usage', 'Last Used'],
            $templates->map(function ($template) {
                return [
                    $template->id,
                    $template->display_name,
                    $template->category,
                    $this->getStatusDisplay($template),
                    implode(', ', $template->channels ?? []),
                    $template->usage_count,
                    $template->last_used_at?->diffForHumans() ?? 'Never',
                ];
            })
        );

        return 0;
    }

    protected function createTemplate(TemplateManager $templateManager): int
    {
        if (!$this->option('interactive')) {
            $this->error('Template creation requires --interactive flag');
            return 1;
        }

        $this->info('Creating new message template...');

        // Get template type
        $patterns = ['simple_otp', 'welcome_message', 'reminder_notification', 'custom'];
        $pattern = $this->choice('Select template pattern', $patterns, 'custom');

        $data = [];

        if ($pattern === 'custom') {
            $data = $this->getCustomTemplateData();
        } else {
            $data = $this->getPatternTemplateData($pattern);
        }

        try {
            if ($pattern === 'custom') {
                $template = Template::create($data);
            } else {
                $template = $templateManager->createFromPattern($pattern, $data);
            }

            $this->info("Template created successfully: {$template->name} (ID: {$template->id})");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create template: " . $e->getMessage());
            return 1;
        }
    }

    protected function approveTemplate(TemplateService $templateService): int
    {
        $template = $this->getTemplateFromArgument();
        if (!$template) return 1;

        $approver = $this->ask('Approved by', 'system');
        $notes = $this->ask('Approval notes (optional)', '');

        if ($templateService->approve($template, $approver, $notes)) {
            $this->info("Template '{$template->name}' approved successfully");
            return 0;
        }

        $this->error('Failed to approve template');
        return 1;
    }

    protected function rejectTemplate(TemplateService $templateService): int
    {
        $template = $this->getTemplateFromArgument();
        if (!$template) return 1;

        $rejector = $this->ask('Rejected by', 'system');
        $reason = $this->ask('Rejection reason', 'Does not meet requirements');

        if ($templateService->reject($template, $rejector, $reason)) {
            $this->info("Template '{$template->name}' rejected successfully");
            return 0;
        }

        $this->error('Failed to reject template');
        return 1;
    }

    protected function duplicateTemplate(TemplateManager $templateManager): int
    {
        $template = $this->getTemplateFromArgument();
        if (!$template) return 1;

        $modifications = [];

        if ($this->option('interactive')) {
            $modifications['display_name'] = $this->ask('New display name', $template->display_name . ' (Copy)');
            $modifications['description'] = $this->ask('New description', $template->description);

            if ($this->confirm('Modify template content?', false)) {
                $modifications['body'] = $this->ask('New template body', $template->body);
            }
        }

        try {
            $newTemplate = $templateManager->duplicateTemplate($template, $modifications);
            $this->info("Template duplicated successfully: {$newTemplate->name} (ID: {$newTemplate->id})");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to duplicate template: " . $e->getMessage());
            return 1;
        }
    }

    protected function showStatistics(TemplateManager $templateManager): int
    {
        $stats = $templateManager->getStatistics();

        $this->info('Template Statistics:');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Templates', $stats['total_templates']],
                ['Active Templates', $stats['active_templates']],
                ['Pending Approval', $stats['pending_approval']],
            ]
        );

        if (!empty($stats['by_category'])) {
            $this->newLine();
            $this->info('Templates by Category:');
            $this->table(
                ['Category', 'Count'],
                collect($stats['by_category'])->map(fn($count, $category) => [$category, $count])
            );
        }

        if (!empty($stats['by_channel'])) {
            $this->newLine();
            $this->info('Templates by Channel:');
            $this->table(
                ['Channel', 'Count'],
                collect($stats['by_channel'])->map(fn($count, $channel) => [$channel, $count])
            );
        }

        if (!empty($stats['most_used'])) {
            $this->newLine();
            $this->info('Most Used Templates:');
            $this->table(
                ['Template', 'Usage Count'],
                collect($stats['most_used'])->map(fn($template) => [$template['display_name'], $template['usage_count']])
            );
        }

        return 0;
    }

    protected function cleanupTemplates(TemplateManager $templateManager): int
    {
        $outdated = $templateManager->findOutdatedTemplates();

        if ($outdated->isEmpty()) {
            $this->info('No outdated templates found');
            return 0;
        }

        $this->info("Found {$outdated->count()} potentially outdated templates:");

        $this->table(
            ['ID', 'Name', 'Last Used', 'Usage Count'],
            $outdated->map(fn($t) => [
                $t->id,
                $t->display_name,
                $t->last_used_at?->diffForHumans() ?? 'Never',
                $t->usage_count
            ])
        );

        if ($this->confirm('Mark these templates as inactive?', false)) {
            foreach ($outdated as $template) {
                $template->update(['is_active' => false]);
            }
            $this->info("Marked {$outdated->count()} templates as inactive");
        }

        return 0;
    }

    protected function showHelp(): int
    {
        $this->info('Available actions:');
        $this->line('  list      - List all templates with optional filters');
        $this->line('  create    - Create new template (requires --interactive)');
        $this->line('  approve   - Approve a pending template');
        $this->line('  reject    - Reject a pending template');
        $this->line('  duplicate - Duplicate an existing template');
        $this->line('  stats     - Show template statistics');
        $this->line('  cleanup   - Find and mark outdated templates as inactive');

        $this->newLine();
        $this->info('Examples:');
        $this->line('  php artisan messenger:templates list --category=otp');
        $this->line('  php artisan messenger:templates create --interactive');
        $this->line('  php artisan messenger:templates approve welcome_new_member');
        $this->line('  php artisan messenger:templates stats');

        return 0;
    }

    protected function getCustomTemplateData(): array
    {
        return [
            'name' => $this->ask('Template name (unique)'),
            'display_name' => $this->ask('Display name'),
            'description' => $this->ask('Description'),
            'category' => $this->choice('Category', ['otp', 'welcome', 'verification', 'notification', 'marketing', 'transactional', 'emergency']),
            'type' => $this->choice('Type', ['transactional', 'marketing', 'emergency']),
            'channels' => $this->askChannels(),
            'body' => $this->ask('Template body (use {{ variable }} for variables)'),
            'language' => $this->choice('Language', ['en', 'ar'], 'en'),
            'is_active' => false,
            'approval_status' => 'pending',
            'version' => 1,
        ];
    }

    protected function getPatternTemplateData(string $pattern): array
    {
        $data = [
            'display_name' => $this->ask('Display name'),
            'description' => $this->ask('Description (optional)', ''),
        ];

        if ($this->confirm('Customize template content?', false)) {
            $data['body'] = $this->ask('Template body');
        }

        return $data;
    }

    protected function askChannels(): array
    {
        $availableChannels = ['sms', 'whatsapp'];
        $channels = [];

        foreach ($availableChannels as $channel) {
            if ($this->confirm("Support {$channel}?", $channel === 'sms')) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    protected function getTemplateFromArgument(): ?Template
    {
        $templateInput = $this->argument('template');

        if (!$templateInput) {
            $this->error('Template name or ID is required');
            return null;
        }

        // Try to find by ID first
        if (is_numeric($templateInput)) {
            $template = Template::find($templateInput);
            if ($template) return $template;
        }

        // Try to find by name
        $template = Template::where('name', $templateInput)->first();

        if (!$template) {
            $this->error("Template not found: {$templateInput}");
            return null;
        }

        return $template;
    }

    protected function getStatusDisplay(Template $template): string
    {
        if (!$template->is_active) {
            return '<comment>Inactive</comment>';
        }

        return match($template->approval_status) {
            'approved' => '<info>Approved</info>',
            'pending' => '<comment>Pending</comment>',
            'rejected' => '<error>Rejected</error>',
            default => '<comment>Unknown</comment>',
        };
    }
}
