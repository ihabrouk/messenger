<?php

namespace App\Messenger\Database\Factories;

use App\Messenger\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Template Factory
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        $content = $this->faker->paragraph();

        // Add some variables to the content
        $variableContent = str_replace(
            [$this->faker->word(), $this->faker->word()],
            ['{{first_name}}', '{{company}}'],
            $content
        );

        return [
            'template_id' => 'tpl_' . $this->faker->unique()->uuid(),
            'name' => ucwords($name),
            'slug' => str_replace(' ', '_', strtolower($name)),
            'description' => $this->faker->sentence(),
            'content' => $variableContent,
            'variables' => ['first_name', 'company', 'date'],
            'category' => $this->faker->randomElement(['marketing', 'notification', 'reminder', 'welcome', 'alert']),
            'language' => $this->faker->randomElement(['en', 'ar', 'fr']),
            'status' => $this->faker->randomElement(['draft', 'active', 'archived']),
            'is_approved' => $this->faker->boolean(80),
            'approved_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
            'approved_by' => $this->faker->optional(0.8)->name(),
            'version' => $this->faker->numberBetween(1, 5),
            'parent_template_id' => null, // Will be set for variants
            'is_default_variant' => true,
            'tags' => $this->faker->optional(0.6)->randomElements(['promotion', 'urgent', 'seasonal', 'automated'], 2),
            'metadata' => $this->faker->optional(0.4)->randomElements([
                'created_by' => $this->faker->name(),
                'department' => $this->faker->randomElement(['marketing', 'support', 'sales']),
                'campaign_type' => $this->faker->randomElement(['email', 'sms', 'both']),
            ]),
            'usage_count' => $this->faker->numberBetween(0, 1000),
            'last_used_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the template is a draft
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * Indicate that the template is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'is_approved' => true,
            'approved_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'approved_by' => $this->faker->name(),
        ]);
    }

    /**
     * Indicate that the template is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    /**
     * Indicate that the template is approved
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
            'approved_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'approved_by' => $this->faker->name(),
        ]);
    }

    /**
     * Indicate that the template is not approved
     */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * Set specific category
     */
    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Marketing template
     */
    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'marketing',
            'content' => 'Hi {{first_name}}, special offer just for you! Get 20% off at {{company}}. Valid until {{expiry_date}}.',
            'variables' => ['first_name', 'company', 'expiry_date'],
            'tags' => ['promotion', 'discount'],
        ]);
    }

    /**
     * Welcome template
     */
    public function welcome(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'welcome',
            'content' => 'Welcome to {{company}}, {{first_name}}! We\'re excited to have you on board.',
            'variables' => ['first_name', 'company'],
            'tags' => ['welcome', 'onboarding'],
        ]);
    }

    /**
     * Reminder template
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'reminder',
            'content' => 'Hi {{first_name}}, this is a reminder about your appointment on {{date}} at {{time}}.',
            'variables' => ['first_name', 'date', 'time'],
            'tags' => ['reminder', 'appointment'],
        ]);
    }

    /**
     * Alert template
     */
    public function alert(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'alert',
            'content' => 'ALERT: {{message}}. Please take immediate action.',
            'variables' => ['message'],
            'tags' => ['alert', 'urgent'],
        ]);
    }

    /**
     * Set specific language
     */
    public function language(string $language): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => $language,
        ]);
    }

    /**
     * Arabic template
     */
    public function arabic(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => 'ar',
            'content' => 'مرحبا {{first_name}}، نحن سعداء لانضمامك إلى {{company}}.',
            'variables' => ['first_name', 'company'],
        ]);
    }

    /**
     * Frequently used template
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(500, 2000),
            'last_used_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Never used template
     */
    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => 0,
            'last_used_at' => null,
        ]);
    }

    /**
     * Recent template
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Template with many variables
     */
    public function withManyVariables(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => 'Hello {{first_name}} {{last_name}}, your order #{{order_id}} from {{company}} will be delivered to {{address}} on {{delivery_date}} at {{delivery_time}}.',
            'variables' => ['first_name', 'last_name', 'order_id', 'company', 'address', 'delivery_date', 'delivery_time'],
        ]);
    }

    /**
     * Simple template with no variables
     */
    public function simple(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => 'Thank you for your purchase! Your order has been received and will be processed soon.',
            'variables' => [],
        ]);
    }

    /**
     * Create a variant of an existing template
     */
    public function variantOf(Template $parentTemplate): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_template_id' => $parentTemplate->id,
            'is_default_variant' => false,
            'name' => $parentTemplate->name . ' - Variant ' . $this->faker->randomLetter(),
            'category' => $parentTemplate->category,
            'language' => $parentTemplate->language,
            'variables' => $parentTemplate->variables,
            'version' => $parentTemplate->version + 1,
        ]);
    }

    /**
     * Default variant
     */
    public function defaultVariant(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default_variant' => true,
        ]);
    }

    /**
     * Multi-language template with common content
     */
    public function multiLanguage(): static
    {
        $languages = ['en', 'ar', 'fr'];
        $contents = [
            'en' => 'Welcome {{first_name}}, thank you for joining {{company}}!',
            'ar' => 'مرحبا {{first_name}}، شكرا لانضمامك إلى {{company}}!',
            'fr' => 'Bienvenue {{first_name}}, merci de rejoindre {{company}}!',
        ];

        $language = $this->faker->randomElement($languages);

        return $this->state(fn (array $attributes) => [
            'language' => $language,
            'content' => $contents[$language],
            'variables' => ['first_name', 'company'],
            'category' => 'welcome',
        ]);
    }
}
