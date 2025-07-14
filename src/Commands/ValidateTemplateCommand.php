<?php

namespace App\Messenger\Commands;

use App\Messenger\Services\TemplateService;
use App\Messenger\Services\TemplateValidator;
use App\Messenger\Models\Template;
use Illuminate\Console\Command;

/**
 * Validate Template Command
 *
 * Validates templates for provider compatibility
 */
class ValidateTemplateCommand extends Command
{
    protected $signature = 'messenger:validate-template
                           {template? : Template name or ID}
                           {--provider= : Specific provider to validate against}
                           {--all : Validate all templates}
                           {--fix : Attempt to fix validation issues}';

    protected $description = 'Validate message templates for provider compatibility';

    public function handle(TemplateValidator $validator): int
    {
        if ($this->option('all')) {
            return $this->validateAllTemplates($validator);
        }

        $templateInput = $this->argument('template');
        if (!$templateInput) {
            $templateInput = $this->ask('Enter template name or ID');
        }

        $template = $this->findTemplate($templateInput);
        if (!$template) {
            $this->error("Template not found: {$templateInput}");
            return 1;
        }

        return $this->validateSingleTemplate($template, $validator);
    }

    protected function validateAllTemplates(TemplateValidator $validator): int
    {
        $this->info('Validating all templates...');

        $templates = Template::all();
        $summary = ['valid' => 0, 'warnings' => 0, 'errors' => 0];

        $this->output->progressStart($templates->count());

        foreach ($templates as $template) {
            $this->output->progressAdvance();

            $hasErrors = false;
            $hasWarnings = false;

            if ($this->option('provider')) {
                $result = $validator->validateForProvider($template, $this->option('provider'));
                if (!empty($result['errors'])) $hasErrors = true;
                if (!empty($result['warnings'])) $hasWarnings = true;
            } else {
                $results = $validator->validateForAllProviders($template);
                foreach ($results as $result) {
                    if (!empty($result['errors'])) $hasErrors = true;
                    if (!empty($result['warnings'])) $hasWarnings = true;
                }
            }

            if ($hasErrors) {
                $summary['errors']++;
            } elseif ($hasWarnings) {
                $summary['warnings']++;
            } else {
                $summary['valid']++;
            }
        }

        $this->output->progressFinish();

        // Display summary
        $this->newLine();
        $this->info('Validation Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Valid', $summary['valid']],
                ['Warnings', $summary['warnings']],
                ['Errors', $summary['errors']],
                ['Total', $templates->count()],
            ]
        );

        return $summary['errors'] > 0 ? 1 : 0;
    }

    protected function validateSingleTemplate(Template $template, TemplateValidator $validator): int
    {
        $this->info("Validating template: {$template->display_name} ({$template->name})");
        $this->newLine();

        $hasErrors = false;
        $provider = $this->option('provider');

        if ($provider) {
            $result = $validator->validateForProvider($template, $provider);
            $this->displayValidationResult($provider, $result);
            if (!empty($result['errors'])) $hasErrors = true;
        } else {
            $results = $validator->validateForAllProviders($template);
            foreach ($results as $providerName => $result) {
                $this->displayValidationResult($providerName, $result);
                if (!empty($result['errors'])) $hasErrors = true;
                $this->newLine();
            }
        }

        // Display content analysis
        $analysis = $validator->analyzeContent($template->body);
        $this->displayContentAnalysis($analysis);

        if ($this->option('fix') && $hasErrors) {
            $this->attemptFix($template);
        }

        return $hasErrors ? 1 : 0;
    }

    protected function displayValidationResult(string $provider, array $result): void
    {
        $status = $result['valid'] ? '<info>✓ VALID</info>' : '<error>✗ INVALID</error>';
        $this->line("Provider: <comment>{$provider}</comment> - {$status}");

        if (!empty($result['errors'])) {
            $this->line('<error>Errors:</error>');
            foreach ($result['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        if (!empty($result['warnings'])) {
            $this->line('<comment>Warnings:</comment>');
            foreach ($result['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }

        $this->line("Characters: {$result['character_count']}, Segments: {$result['estimated_segments']}, Cost: $" . number_format($result['estimated_cost'], 4));
    }

    protected function displayContentAnalysis(array $analysis): void
    {
        $this->newLine();
        $this->info('Content Analysis:');

        $this->table(
            ['Property', 'Value'],
            [
                ['Character Count', $analysis['character_count']],
                ['Encoding', $analysis['encoding']],
                ['Unicode', $analysis['is_unicode'] ? 'Yes' : 'No'],
                ['Language', $analysis['language_detected']],
                ['Contains Emojis', $analysis['contains_emojis'] ? 'Yes' : 'No'],
                ['Contains Links', $analysis['contains_links'] ? 'Yes' : 'No'],
                ['SMS Segments (GSM)', $analysis['sms_segments']['gsm']],
                ['SMS Segments (Unicode)', $analysis['sms_segments']['unicode']],
                ['Variables Found', implode(', ', $analysis['variables_found'])],
            ]
        );
    }

    protected function attemptFix(Template $template): void
    {
        $this->info('Attempting to fix validation issues...');

        $fixed = false;

        // Try to fix common issues
        $body = $template->body;

        // Remove excessive whitespace
        $newBody = preg_replace('/\s+/', ' ', trim($body));
        if ($newBody !== $body) {
            $this->line('• Cleaned up whitespace');
            $body = $newBody;
            $fixed = true;
        }

        // Fix malformed variables
        $newBody = preg_replace('/\{\{\s*([^}]+)\s*\}\}/', '{{ $1 }}', $body);
        if ($newBody !== $body) {
            $this->line('• Fixed variable formatting');
            $body = $newBody;
            $fixed = true;
        }

        if ($fixed) {
            if ($this->confirm('Apply fixes to template?')) {
                $template->update(['body' => $body]);
                $this->info('Template updated successfully');
            }
        } else {
            $this->warn('No automatic fixes available');
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
