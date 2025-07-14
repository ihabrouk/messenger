<?php

namespace App\Messenger\Services;

use App\Messenger\Models\Template;
use App\Messenger\Enums\MessageProvider;

/**
 * Template Validator
 *
 * Advanced template validation with provider-specific rules
 */
class TemplateValidator
{
    /**
     * Provider-specific validation rules
     */
    protected array $providerRules = [
        'sms_misr' => [
            'max_sms_length' => 1600,
            'max_unicode_length' => 640,
            'single_sms_length' => 160,
            'single_unicode_length' => 70,
            'forbidden_chars' => [],
            'required_encoding' => 'UTF-8',
        ],
        'twilio' => [
            'max_sms_length' => 1600,
            'max_unicode_length' => 640,
            'single_sms_length' => 160,
            'single_unicode_length' => 70,
            'max_whatsapp_length' => 4096,
            'forbidden_chars' => [],
        ],
        'mocktest' => [
            'max_sms_length' => 9999,
            'max_unicode_length' => 9999,
            'single_sms_length' => 160,
            'single_unicode_length' => 70,
            'forbidden_chars' => [],
        ],
    ];

    /**
     * Validate template for all supported providers
     */
    public function validateForAllProviders(Template $template): array
    {
        $results = [];

        foreach (array_keys($this->providerRules) as $provider) {
            $results[$provider] = $this->validateForProvider($template, $provider);
        }

        return $results;
    }

    /**
     * Validate template for specific provider
     */
    public function validateForProvider(Template $template, string $provider): array
    {
        $rules = $this->providerRules[$provider] ?? $this->providerRules['sms_misr'];
        $errors = [];
        $warnings = [];

        // Get rendered length with sample data
        $renderedLength = $this->getRenderedLength($template);
        $isUnicode = $this->containsUnicode($template->body);

        // Channel-specific validation
        $channels = $template->channels ?? ['sms'];

        foreach ($channels as $channel) {
            $channelErrors = $this->validateChannel($template, $channel, $provider, $rules);
            $errors = array_merge($errors, $channelErrors);
        }

        // Length validation
        if ($isUnicode) {
            $maxLength = $rules['max_unicode_length'];
            $singleLength = $rules['single_unicode_length'];
        } else {
            $maxLength = $rules['max_sms_length'];
            $singleLength = $rules['single_sms_length'];
        }

        if ($renderedLength > $maxLength) {
            $errors[] = "Template too long for {$provider}: {$renderedLength} chars (max: {$maxLength})";
        }

        if ($renderedLength > $singleLength) {
            $segments = ceil($renderedLength / $singleLength);
            $warnings[] = "Template will be sent as {$segments} SMS segments on {$provider}";
        }

        // Character validation
        if (!empty($rules['forbidden_chars'])) {
            $forbiddenFound = [];
            foreach ($rules['forbidden_chars'] as $char) {
                if (str_contains($template->body, $char)) {
                    $forbiddenFound[] = $char;
                }
            }
            if (!empty($forbiddenFound)) {
                $errors[] = "Contains forbidden characters for {$provider}: " . implode(', ', $forbiddenFound);
            }
        }

        // Variable validation
        $variableErrors = $this->validateVariables($template, $provider);
        $errors = array_merge($errors, $variableErrors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'estimated_segments' => $isUnicode ?
                ceil($renderedLength / $rules['single_unicode_length']) :
                ceil($renderedLength / $rules['single_sms_length']),
            'estimated_cost' => $this->estimateCost($template, $provider, $renderedLength),
            'character_count' => $renderedLength,
            'is_unicode' => $isUnicode,
        ];
    }

    /**
     * Validate template for specific channel
     */
    protected function validateChannel(Template $template, string $channel, string $provider, array $rules): array
    {
        $errors = [];

        switch ($channel) {
            case 'whatsapp':
                if ($provider === 'twilio') {
                    $renderedLength = $this->getRenderedLength($template);
                    if ($renderedLength > $rules['max_whatsapp_length']) {
                        $errors[] = "WhatsApp message too long: {$renderedLength} chars (max: {$rules['max_whatsapp_length']})";
                    }
                } elseif ($provider === 'sms_misr') {
                    $errors[] = "SMS Misr does not support WhatsApp messaging";
                }
                break;

            case 'sms':
                // SMS is supported by all providers
                break;

            default:
                $errors[] = "Unsupported channel: {$channel}";
        }

        return $errors;
    }

    /**
     * Validate template variables
     */
    protected function validateVariables(Template $template, string $provider): array
    {
        $errors = [];
        $variables = $template->variables ?? [];

        // Check for reserved variable names
        $reservedNames = ['provider', 'channel', 'timestamp', 'message_id'];
        foreach ($variables as $variable) {
            if (in_array(strtolower($variable), $reservedNames)) {
                $errors[] = "Variable '{$variable}' is reserved and cannot be used";
            }
        }

        // Check variable naming convention
        foreach ($variables as $variable) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $variable)) {
                $errors[] = "Variable '{$variable}' has invalid naming (use letters, numbers, underscore only)";
            }
        }

        // Provider-specific variable limitations
        if ($provider === 'sms_misr' && count($variables) > 20) {
            $errors[] = "SMS Misr supports maximum 20 variables, found " . count($variables);
        }

        return $errors;
    }

    /**
     * Get content analysis
     */
    public function analyzeContent(string $content): array
    {
        $isUnicode = $this->containsUnicode($content);
        $charCount = mb_strlen($content, 'UTF-8');

        return [
            'character_count' => $charCount,
            'is_unicode' => $isUnicode,
            'encoding' => $isUnicode ? 'UTF-8' : 'GSM 7-bit',
            'sms_segments' => [
                'gsm' => max(1, ceil($charCount / 160)),
                'unicode' => max(1, ceil($charCount / 70)),
            ],
            'variables_found' => $this->extractVariables($content),
            'language_detected' => $this->detectLanguage($content),
            'contains_emojis' => $this->containsEmojis($content),
            'contains_links' => $this->containsLinks($content),
        ];
    }

    /**
     * Check if content contains Unicode characters
     */
    protected function containsUnicode(string $content): bool
    {
        // Check for non-ASCII characters
        return !mb_check_encoding($content, 'ASCII') || preg_match('/[^\x00-\x7F]/', $content);
    }

    /**
     * Check if content contains emojis
     */
    protected function containsEmojis(string $content): bool
    {
        // Basic emoji detection pattern
        return preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]/u', $content);
    }

    /**
     * Check if content contains links
     */
    protected function containsLinks(string $content): bool
    {
        return preg_match('/https?:\/\/[^\s]+/', $content) || str_contains($content, 'www.');
    }

    /**
     * Detect content language
     */
    protected function detectLanguage(string $content): string
    {
        // Basic language detection
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $content)) {
            return 'ar'; // Arabic
        }

        return 'en'; // Default to English
    }

    /**
     * Extract variables from content
     */
    protected function extractVariables(string $content): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Get rendered length with sample data
     */
    protected function getRenderedLength(Template $template): int
    {
        $content = $template->body;
        $sampleData = $template->sample_data ?? [];

        // Replace variables with sample data
        foreach ($sampleData as $key => $value) {
            $content = str_replace("{{ {$key} }}", (string) $value, $content);
        }

        // Replace remaining variables with placeholder
        $content = preg_replace('/\{\{\s*\w+\s*\}\}/', 'PLACEHOLDER', $content);

        return mb_strlen($content, 'UTF-8');
    }

    /**
     * Estimate cost for provider
     */
    protected function estimateCost(Template $template, string $provider, int $renderedLength): float
    {
        $rules = $this->providerRules[$provider] ?? $this->providerRules['sms_misr'];
        $isUnicode = $this->containsUnicode($template->body);

        $singleLength = $isUnicode ? $rules['single_unicode_length'] : $rules['single_sms_length'];
        $segments = max(1, ceil($renderedLength / $singleLength));

        // Basic cost calculation (in cents)
        $costPerSegment = match($provider) {
            'sms_misr' => 2.5,
            'twilio' => 7.5,
            'mocktest' => 0.0,
            default => 5.0,
        };

        return ($segments * $costPerSegment) / 100; // Convert to dollars
    }

    /**
     * Get validation summary for dashboard
     */
    public function getValidationSummary(): array
    {
        $templates = Template::all();
        $summary = [
            'total' => $templates->count(),
            'valid' => 0,
            'warnings' => 0,
            'errors' => 0,
            'by_provider' => [],
        ];

        foreach ($templates as $template) {
            $hasErrors = false;
            $hasWarnings = false;

            foreach (array_keys($this->providerRules) as $provider) {
                $validation = $this->validateForProvider($template, $provider);

                if (!empty($validation['errors'])) {
                    $hasErrors = true;
                }
                if (!empty($validation['warnings'])) {
                    $hasWarnings = true;
                }

                $summary['by_provider'][$provider] ??= ['valid' => 0, 'warnings' => 0, 'errors' => 0];

                if ($validation['valid']) {
                    $summary['by_provider'][$provider]['valid']++;
                } elseif (!empty($validation['warnings'])) {
                    $summary['by_provider'][$provider]['warnings']++;
                } else {
                    $summary['by_provider'][$provider]['errors']++;
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

        return $summary;
    }
}
