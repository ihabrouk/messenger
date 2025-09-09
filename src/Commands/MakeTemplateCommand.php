<?php

namespace Ihabrouk\Messenger\Commands;

use Exception;
use Illuminate\Console\Command;
use Ihabrouk\Messenger\Models\MessageTemplate;
use Ihabrouk\Messenger\Enums\TemplateCategory;
use Ihabrouk\Messenger\Enums\MessageType;
use Ihabrouk\Messenger\Enums\MessageLanguage;

class MakeTemplateCommand extends Command
{
    protected $signature = 'messenger:make-template
                            {key : The template key}
                            {--category= : Template category}
                            {--type= : Message type}
                            {--language= : Template language}
                            {--content= : Template content}';

    protected $description = 'Create a new message template';

    public function handle()
    {
        $key = $this->argument('key');

        if (MessageTemplate::where('key', $key)->exists()) {
            $this->error("Template with key '{$key}' already exists!");
            return 1;
        }

        $category = $this->option('category') ?: $this->choice(
            'Select template category:',
            array_map(fn($cat) => $cat->value, TemplateCategory::cases()),
            0
        );

        $type = $this->option('type') ?: $this->choice(
            'Select message type:',
            array_map(fn($type) => $type->value, MessageType::cases()),
            0
        );

        $language = $this->option('language') ?: $this->choice(
            'Select language:',
            array_map(fn($lang) => $lang->value, MessageLanguage::cases()),
            0
        );

        $content = $this->option('content') ?: $this->ask('Enter template content:');

        if (empty($content)) {
            $this->error('Template content cannot be empty!');
            return 1;
        }

        try {
            $template = MessageTemplate::create([
                'key' => $key,
                'name' => ucwords(str_replace(['_', '-'], ' ', $key)),
                'content' => $content,
                'category' => TemplateCategory::from($category),
                'type' => MessageType::from($type),
                'language' => MessageLanguage::from($language),
                'is_active' => true,
                'variables' => $this->extractVariables($content),
            ]);

            $this->info("Template '{$key}' created successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $template->id],
                    ['Key', $template->key],
                    ['Name', $template->name],
                    ['Category', $template->category->label()],
                    ['Type', $template->type->label()],
                    ['Language', $template->language->label()],
                    ['Variables', implode(', ', $template->variables ?: [])],
                ]
            );

            return 0;
        } catch (Exception $e) {
            $this->error("Failed to create template: {$e->getMessage()}");
            return 1;
        }
    }

    protected function extractVariables(string $content): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }
}
