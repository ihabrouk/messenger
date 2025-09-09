<?php

namespace Ihabrouk\Messenger\Commands;

use Exception;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Enums\MessageType;
use Ihabrouk\Messenger\Services\MessageProviderFactory;
use Illuminate\Console\Command;

class ProcessWebhookCommand extends Command
{
    protected $signature = 'messenger:process-webhook {provider} {--payload=} {--signature=}';
    protected $description = 'Process a webhook payload for testing purposes';

    public function handle(MessageProviderFactory $factory): int
    {
        $providerName = $this->argument('provider');
        $payload = $this->option('payload') ? json_decode($this->option('payload'), true) : [];
        $signature = $this->option('signature');

        try {
            $provider = $factory->make($providerName);

            $this->info("Processing webhook for {$providerName} provider...");

            // Verify webhook signature
            $isValid = $provider->verifyWebhook($payload, $signature);

            if ($isValid) {
                $this->info("✓ Webhook signature is valid");
                $this->info("Payload: " . json_encode($payload, JSON_PRETTY_PRINT));
            } else {
                $this->error("✗ Webhook signature is invalid");
            }

            return $isValid ? Command::SUCCESS : Command::FAILURE;

        } catch (Exception $e) {
            $this->error("Error processing webhook: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
