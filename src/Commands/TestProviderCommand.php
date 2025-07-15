<?php

namespace Ihabrouk\Messenger\Commands;

use Ihabrouk\Messenger\Services\MessageProviderFactory;
use Ihabrouk\Messenger\Testing\ProviderTestHelper;
use Ihabrouk\Messenger\Testing\MockMessageProvider;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Enums\MessageType;
use Ihabrouk\Messenger\Enums\MessageProvider;
use Illuminate\Console\Command;

class TestProviderCommand extends Command
{
    protected $signature = 'messenger:test-provider {provider} {--recipient=} {--message=} {--comprehensive} {--mock}';
    protected $description = 'Test a specific message provider';

    public function handle(MessageProviderFactory $factory): int
    {
        $providerName = $this->argument('provider');
        $recipient = $this->option('recipient') ?? '+201234567890';
        $message = $this->option('message') ?? 'Test message from ' . $providerName;
        $comprehensive = $this->option('comprehensive');
        $useMock = $this->option('mock');

        try {
            if ($useMock) {
                $this->info("Using mock provider for testing...");
                $provider = new MockMessageProvider();
            } else {
                $provider = $factory->make($providerName);
            }

            $this->info("Testing {$providerName} provider...");

            if ($comprehensive) {
                return $this->runComprehensiveTests($provider);
            } else {
                return $this->runBasicTest($provider, $recipient, $message);
            }

        } catch (\Exception $e) {
            $this->error("Error testing provider: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Run comprehensive tests using ProviderTestHelper
     */
    private function runComprehensiveTests($provider): int
    {
        $this->info("Running comprehensive tests...");

        $testHelper = new ProviderTestHelper($provider);
        $results = $testHelper->runTests();

        // Display summary
        $summary = $testHelper->getTestSummary();
        $this->info("\n=== Test Summary ===");
        $this->info("Provider: {$summary['provider']}");
        $this->info("Total Tests: {$summary['total_tests']}");
        $this->info("Passed: {$summary['passed']}");
        $this->info("Failed: {$summary['failed']}");
        $this->info("Success Rate: {$summary['success_rate']}%");

        // Display detailed results
        $this->info("\n=== Detailed Results ===");
        foreach ($results as $testName => $result) {
            $status = $result['status'] === 'passed' ? '<info>PASS</info>' : '<error>FAIL</error>';
            $this->line("{$status} {$testName}: {$result['message']}");

            if ($result['status'] === 'failed' && isset($result['error'])) {
                $this->line("  Error: {$result['error']}");
            }
        }

        return $summary['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Run basic test (original functionality)
     */
    private function runBasicTest($provider, string $recipient, string $message): int
    {
        // Test health check
        $this->info('Checking provider health...');
        $isHealthy = $provider->isHealthy();
        $this->info("Provider health: " . ($isHealthy ? 'OK' : 'FAILED'));

        // Test balance
        $this->info('Checking balance...');
        $balance = $provider->getBalance();
        $this->info("Current balance: {$balance}");

        // Test message sending
        $this->info("Sending test message to {$recipient}...");
        $messageData = new SendMessageData(
            to: $recipient,
            message: $message,
            type: MessageType::SMS
        );

        $response = $provider->send($messageData);

        if ($response->success) {
            $this->info("Message sent successfully!");
            $this->info("Message ID: {$response->providerMessageId}");
            $this->info("Status: {$response->status->value}");
            $this->info("Cost: {$response->cost}");
            return Command::SUCCESS;
        } else {
            $this->error("Failed to send message");
            $this->error("Error Code: {$response->errorCode}");
            $this->error("Error Message: {$response->errorMessage}");
            return Command::FAILURE;
        }
    }
}
