<?php

namespace Ihabrouk\Messenger\Commands;

use Ihabrouk\Messenger\Services\TemplateService;
use Ihabrouk\Messenger\Models\Template;
use Illuminate\Console\Command;

/**
 * Preview Template Command
 *
 * Preview templates with sample or custom data
 */
class PreviewTemplateCommand extends Command
{
    protected $signature = 'messenger:preview-template
                           {template : Template name or ID}
                           {--vars= : JSON string of variables}
                           {--sample : Use sample data only}
                           {--interactive : Interactive variable input}';

    protected $description = 'Preview message template with variables';

    public function handle(TemplateService $templateService): int
    {
        $templateInput = $this->argument('template');
        $template = $this->findTemplate($templateInput);

        if (!$template) {
            $this->error("Template not found: {$templateInput}");
            return 1;
        }

        $this->displayTemplateInfo($template);

        $variables = $this->getVariables($template);

        try {
            $preview = $templateService->preview($template, $variables);
            $this->displayPreview($preview);

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to preview template: " . $e->getMessage());
            return 1;
        }
    }

    protected function displayTemplateInfo(Template $template): void
    {
        $this->info("Template: {$template->display_name}");
        $this->line("Name: {$template->name}");
        $this->line("Category: {$template->category}");
        $this->line("Channels: " . implode(', ', $template->channels ?? []));
        $this->line("Language: {$template->language}");
        $this->newLine();

        $this->line("Template Body:");
        $this->line($template->body);
        $this->newLine();

        if (!empty($template->variables)) {
            $this->line("Required Variables: " . implode(', ', $template->variables));
        }

        if (!empty($template->sample_data)) {
            $this->line("Sample Data Available: " . implode(', ', array_keys($template->sample_data)));
        }

        $this->newLine();
    }

    protected function getVariables(Template $template): array
    {
        if ($this->option('sample')) {
            return $template->sample_data ?? [];
        }

        if ($this->option('vars')) {
            $varsJson = $this->option('vars');
            $variables = json_decode($varsJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON for variables');
                return [];
            }

            return array_merge($template->sample_data ?? [], $variables);
        }

        if ($this->option('interactive')) {
            return $this->getInteractiveVariables($template);
        }

        // Default to sample data
        return $template->sample_data ?? [];
    }

    protected function getInteractiveVariables(Template $template): array
    {
        $variables = $template->sample_data ?? [];
        $requiredVars = $template->variables ?? [];

        $this->info('Enter variable values (press Enter to use sample data):');

        foreach ($requiredVars as $variable) {
            $sampleValue = $variables[$variable] ?? '';
            $prompt = "Enter value for '{$variable}'";

            if ($sampleValue) {
                $prompt .= " (sample: {$sampleValue})";
            }

            $value = $this->ask($prompt, $sampleValue);

            if ($value !== '') {
                $variables[$variable] = $value;
            }
        }

        // Ask for additional variables
        while ($this->confirm('Add another variable?', false)) {
            $name = $this->ask('Variable name');
            $value = $this->ask('Variable value');

            if ($name && $value !== '') {
                $variables[$name] = $value;
            }
        }

        return $variables;
    }

    protected function displayPreview(array $preview): void
    {
        if (!$preview['success']) {
            $this->error("Preview failed: " . $preview['error']);
            return;
        }

        $this->info('Preview Result:');
        $this->newLine();

        // Display rendered message in a box
        $this->line('┌' . str_repeat('─', 60) . '┐');

        $lines = explode("\n", wordwrap($preview['rendered'], 58));
        foreach ($lines as $line) {
            $this->line('│ ' . str_pad($line, 58) . ' │');
        }

        $this->line('└' . str_repeat('─', 60) . '┘');
        $this->newLine();

        // Display statistics
        $this->table(
            ['Metric', 'Value'],
            [
                ['Character Count', $preview['character_count']],
                ['SMS Segments', $preview['sms_segments']],
                ['Estimated Cost', '$' . number_format($preview['estimated_cost'], 4)],
            ]
        );

        // Display variables used
        if (!empty($preview['variables_used'])) {
            $this->newLine();
            $this->info('Variables Used:');
            foreach ($preview['variables_used'] as $key => $value) {
                $this->line("  {$key}: {$value}");
            }
        }
    }

    protected function findTemplate(string $input): ?Template
    {
        // Try to find by ID first
        if (is_numeric($input)) {
            $template = Template::find($input);
            if ($template) return $template;
        }

        // Try to find by name
        return Template::where('name', $input)->first();
    }
}
