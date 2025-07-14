<?php

namespace App\Messenger\Commands;

use App\Messenger\Services\MessengerService;
use App\Messenger\Models\Template;
use Illuminate\Console\Command;

/**
 * Send Message Command
 *
 * Send messages via command line
 */
class SendMessageCommand extends Command
{
    protected $signature = 'messenger:send
                           {phone : Recipient phone number}
                           {message? : Message content (required if no template)}
                           {--template= : Template name to use}
                           {--provider= : Provider to use (sms_misr, twilio, mocktest)}
                           {--type=transactional : Message type}
                           {--vars= : Template variables as JSON}
                           {--bulk : Send to multiple numbers (comma-separated)}';

    protected $description = 'Send SMS messages via command line';

    public function handle(MessengerService $messenger): int
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message');
        $templateName = $this->option('template');
        $provider = $this->option('provider');
        $type = $this->option('type');
        $varsJson = $this->option('vars');
        $isBulk = $this->option('bulk');

        // Parse variables
        $variables = [];
        if ($varsJson) {
            try {
                $variables = json_decode($varsJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->error("Invalid JSON in --vars option: " . $e->getMessage());
                return 1;
            }
        }

        // Handle bulk sending
        if ($isBulk) {
            return $this->handleBulkSend($messenger, $phone, $message, $templateName, $provider, $type, $variables);
        }

        // Validate inputs
        if (!$message && !$templateName) {
            $this->error('Either message content or template is required');
            return 1;
        }

        try {
            if ($templateName) {
                return $this->sendWithTemplate($messenger, $phone, $templateName, $provider, $type, $variables);
            } else {
                return $this->sendDirectMessage($messenger, $phone, $message, $provider, $type);
            }
        } catch (\Exception $e) {
            $this->error('Failed to send message: ' . $e->getMessage());
            return 1;
        }
    }

    protected function sendWithTemplate(
        MessengerService $messenger,
        string $phone,
        string $templateName,
        ?string $provider,
        string $type,
        array $variables
    ): int {
        $template = Template::where('name', $templateName)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->first();

        if (!$template) {
            $this->error("Template '{$templateName}' not found or not active/approved");

            // Show available templates
            $availableTemplates = Template::where('is_active', true)
                ->where('approval_status', 'approved')
                ->pluck('name', 'display_name');

            if ($availableTemplates->isNotEmpty()) {
                $this->line('Available templates:');
                foreach ($availableTemplates as $display => $name) {
                    $this->line("  - {$name} ({$display})");
                }
            }

            return 1;
        }

        // Check if all required variables are provided
        $templateVars = $template->variables ?? [];
        $missingVars = array_diff($templateVars, array_keys($variables));

        if (!empty($missingVars)) {
            $this->error('Missing required template variables: ' . implode(', ', $missingVars));

            // Ask for missing variables interactively
            if ($this->confirm('Would you like to provide the missing variables now?')) {
                foreach ($missingVars as $var) {
                    $variables[$var] = $this->ask("Enter value for '{$var}'");
                }
            } else {
                return 1;
            }
        }

        $messageData = [
            'recipient_phone' => $phone,
            'template_id' => $template->id,
            'variables' => $variables,
            'type' => $type,
        ];

        if ($provider) {
            $messageData['provider'] = $provider;
        }

        $result = $messenger->sendFromTemplate($template, $messageData);

        if ($result->isSuccessful()) {
            $this->info("✅ Message sent successfully to {$phone} using template '{$templateName}'");
            $this->line("Message ID: {$result->getMessageId()}");
            if ($result->getExternalId()) {
                $this->line("External ID: {$result->getExternalId()}");
            }
        } else {
            $this->error("❌ Failed to send message: " . $result->getErrorMessage());
            return 1;
        }

        return 0;
    }

    protected function sendDirectMessage(
        MessengerService $messenger,
        string $phone,
        string $message,
        ?string $provider,
        string $type
    ): int {
        $messageData = [
            'recipient_phone' => $phone,
            'content' => $message,
            'type' => $type,
        ];

        if ($provider) {
            $messageData['provider'] = $provider;
        }

        $result = $messenger->send($messageData);

        if ($result->isSuccessful()) {
            $this->info("✅ Message sent successfully to {$phone}");
            $this->line("Message ID: {$result->getMessageId()}");
            if ($result->getExternalId()) {
                $this->line("External ID: {$result->getExternalId()}");
            }
        } else {
            $this->error("❌ Failed to send message: " . $result->getErrorMessage());
            return 1;
        }

        return 0;
    }

    protected function handleBulkSend(
        MessengerService $messenger,
        string $phoneList,
        ?string $message,
        ?string $templateName,
        ?string $provider,
        string $type,
        array $variables
    ): int {
        $phones = array_map('trim', explode(',', $phoneList));
        $successCount = 0;
        $failCount = 0;

        $this->info("Sending to " . count($phones) . " recipients...");
        $progressBar = $this->output->createProgressBar(count($phones));

        foreach ($phones as $phone) {
            if (empty($phone)) continue;

            try {
                if ($templateName) {
                    $result = $this->sendWithTemplate($messenger, $phone, $templateName, $provider, $type, $variables);
                } else {
                    $result = $this->sendDirectMessage($messenger, $phone, $message, $provider, $type);
                }

                if ($result === 0) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to send to {$phone}: " . $e->getMessage());
                $failCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("📊 Bulk send completed:");
        $this->line("✅ Successful: {$successCount}");
        if ($failCount > 0) {
            $this->line("❌ Failed: {$failCount}");
        }

        return $failCount > 0 ? 1 : 0;
    }
}
