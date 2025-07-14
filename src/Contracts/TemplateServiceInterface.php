<?php

namespace App\Messenger\Contracts;

use App\Messenger\Models\Template;

/**
 * Template Service Interface
 *
 * Handles template rendering, validation, caching, and management
 */
interface TemplateServiceInterface
{
    /**
     * Render template with variables
     */
    public function render(Template $template, array $variables = []): string;

    /**
     * Render template by name/key
     */
    public function renderByName(string $templateName, array $variables = [], array $options = []): string;

    /**
     * Validate template variables
     */
    public function validateVariables(Template $template, array $variables = []): array;

    /**
     * Validate template content and structure
     */
    public function validateTemplate(Template $template): array;

    /**
     * Extract variables from template content
     */
    public function extractVariables(string $content): array;

    /**
     * Get required variables for a template
     */
    public function getRequiredVariables(Template $template): array;

    /**
     * Get cached template
     */
    public function getCachedTemplate(int $templateId): ?Template;

    /**
     * Cache template
     */
    public function cacheTemplate(Template $template): void;

    /**
     * Clear template cache
     */
    public function clearTemplateCache(int $templateId): void;

    /**
     * Clear all template cache
     */
    public function clearAllTemplateCache(): void;

    /**
     * Get template by key/name
     */
    public function getByName(string $name): ?Template;

    /**
     * Get templates by category
     */
    public function getByCategory(string $category): \Illuminate\Database\Eloquent\Collection;

    /**
     * Get templates by channel
     */
    public function getByChannel(string $channel): \Illuminate\Database\Eloquent\Collection;

    /**
     * Calculate message cost
     */
    public function calculateCost(Template $template, int $recipientCount = 1, array $variables = []): float;

    /**
     * Count characters in rendered template
     */
    public function countCharacters(Template $template, array $variables = []): int;

    /**
     * Preview template with sample data
     */
    public function preview(Template $template, array $variables = []): array;

    /**
     * Create template variant for A/B testing
     */
    public function createVariant(Template $template, array $variantData): Template;

    /**
     * Get best performing variant
     */
    public function getBestVariant(string $variantGroup): ?Template;

    /**
     * Update template usage statistics
     */
    public function updateUsageStats(Template $template): void;

    /**
     * Approve template
     */
    public function approve(Template $template, string $approvedBy, string $notes = ''): bool;

    /**
     * Reject template
     */
    public function reject(Template $template, string $rejectedBy, string $reason): bool;

    /**
     * Create new template version
     */
    public function createVersion(Template $template, array $changes): Template;
}
