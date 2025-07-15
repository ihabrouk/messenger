<?php

namespace Ihabrouk\Messenger\Testing;

use Ihabrouk\Messenger\Contracts\MessageProviderInterface;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Enums\MessageStatus;
use Ihabrouk\Messenger\Enums\MessageType;
use Ihabrouk\Messenger\Enums\MessageProvider;
use Illuminate\Support\Collection;

class ProviderTestHelper
{
    private MessageProviderInterface $provider;
    private array $testResults = [];

    public function __construct(MessageProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Run comprehensive tests on the provider
     */
    public function runTests(): array
    {
        $this->testResults = [];

        // Basic functionality tests
        $this->testSendMessage();
        $this->testSendBulkMessage();
        $this->testGetBalance();
        $this->testHealthCheck();
        $this->testSupportedChannels();
        $this->testWebhookVerification();

        // Capability-based tests
        if ($this->provider->supportsCapability('otp')) {
            $this->testOtpCapability();
        }

        if ($this->provider->supportsCapability('whatsapp')) {
            $this->testWhatsAppCapability();
        }

        if ($this->provider->supportsCapability('bulk_messaging')) {
            $this->testBulkCapability();
        }

        if ($this->provider->supportsCapability('scheduling')) {
            $this->testSchedulingCapability();
        }

        return $this->testResults;
    }

    /**
     * Test basic message sending
     */
    private function testSendMessage(): void
    {
        try {
            $messageData = new SendMessageData(
                to: '+201234567890',
                message: 'Test message from ' . $this->provider->getName(),
                type: MessageType::SMS
            );

            $response = $this->provider->send($messageData);

            $this->addTestResult('send_message', [
                'status' => 'passed',
                'message' => 'Successfully sent message',
                'response' => [
                    'success' => $response->success,
                    'message_id' => $response->providerMessageId,
                    'status' => $response->status->value,
                    'cost' => $response->cost,
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('send_message', [
                'status' => 'failed',
                'message' => 'Failed to send message: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test bulk message sending
     */
    private function testSendBulkMessage(): void
    {
        try {
            $messages = [
                new SendMessageData(
                    to: '+201234567890',
                    message: 'Bulk test message 1 from ' . $this->provider->getName(),
                    type: MessageType::SMS
                ),
                new SendMessageData(
                    to: '+201234567891',
                    message: 'Bulk test message 2 from ' . $this->provider->getName(),
                    type: MessageType::SMS
                )
            ];

            $responses = $this->provider->sendBulk($messages);

            $this->addTestResult('send_bulk_message', [
                'status' => 'passed',
                'message' => 'Successfully sent bulk messages',
                'response' => [
                    'count' => count($responses),
                    'all_successful' => collect($responses)->every(fn($r) => $r->success),
                    'message_ids' => collect($responses)->pluck('providerMessageId')->toArray(),
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('send_bulk_message', [
                'status' => 'failed',
                'message' => 'Failed to send bulk messages: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test balance retrieval
     */
    private function testGetBalance(): void
    {
        try {
            $balance = $this->provider->getBalance();

            $this->addTestResult('get_balance', [
                'status' => 'passed',
                'message' => 'Successfully retrieved balance',
                'response' => [
                    'balance' => $balance,
                    'is_numeric' => is_numeric($balance)
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('get_balance', [
                'status' => 'failed',
                'message' => 'Failed to get balance: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test health check
     */
    private function testHealthCheck(): void
    {
        try {
            $isHealthy = $this->provider->isHealthy();

            $this->addTestResult('health_check', [
                'status' => $isHealthy ? 'passed' : 'failed',
                'message' => 'Provider health status: ' . ($isHealthy ? 'healthy' : 'unhealthy'),
                'response' => ['is_healthy' => $isHealthy]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('health_check', [
                'status' => 'failed',
                'message' => 'Health check failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test supported types
     */
    private function testSupportedChannels(): void
    {
        try {
            $channels = $this->provider->getSupportedTypes();

            $this->addTestResult('supported_channels', [
                'status' => 'passed',
                'message' => 'Retrieved supported types',
                'response' => [
                    'channels' => $channels,
                    'count' => count($channels)
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('supported_channels', [
                'status' => 'failed',
                'message' => 'Failed to get supported types: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test webhook verification
     */
    private function testWebhookVerification(): void
    {
        try {
            $isValid = $this->provider->verifyWebhook('{"test": "payload"}', 'test_signature');

            $this->addTestResult('webhook_verification', [
                'status' => 'passed',
                'message' => 'Webhook verification test completed',
                'response' => ['verification_result' => $isValid]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('webhook_verification', [
                'status' => 'failed',
                'message' => 'Webhook verification failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test OTP capability
     */
    private function testOtpCapability(): void
    {
        try {
            $otpData = new SendMessageData(
                to: '+201234567890',
                message: 'Your OTP is: {{otp}}',
                type: MessageType::OTP,
                variables: ['otp' => '123456']
            );

            $response = $this->provider->send($otpData);

            $this->addTestResult('otp_capability', [
                'status' => 'passed',
                'message' => 'Successfully sent OTP message',
                'response' => [
                    'message_id' => $response->providerMessageId,
                    'status' => $response->status->value
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('otp_capability', [
                'status' => 'failed',
                'message' => 'Failed to send OTP: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test WhatsApp capability
     */
    private function testWhatsAppCapability(): void
    {
        try {
            $whatsappData = new SendMessageData(
                to: '+201234567890',
                message: 'Hello from WhatsApp!',
                type: MessageType::WHATSAPP
            );

            $response = $this->provider->send($whatsappData);

            $this->addTestResult('whatsapp_capability', [
                'status' => 'passed',
                'message' => 'Successfully sent WhatsApp message',
                'response' => [
                    'message_id' => $response->providerMessageId,
                    'status' => $response->status->value
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('whatsapp_capability', [
                'status' => 'failed',
                'message' => 'Failed to send WhatsApp message: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test bulk messaging capability
     */
    private function testBulkCapability(): void
    {
        try {
            $messages = [
                new SendMessageData(
                    to: '+201234567890',
                    message: 'Bulk test message 1',
                    type: MessageType::SMS
                ),
                new SendMessageData(
                    to: '+201234567891',
                    message: 'Bulk test message 2',
                    type: MessageType::SMS
                )
            ];

            $responses = $this->provider->sendBulk($messages);

            $this->addTestResult('bulk_capability', [
                'status' => 'passed',
                'message' => 'Successfully tested bulk messaging capability',
                'response' => [
                    'count' => count($responses),
                    'all_successful' => collect($responses)->every(fn($r) => $r->success)
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('bulk_capability', [
                'status' => 'failed',
                'message' => 'Failed to test bulk capability: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test scheduling capability
     */
    private function testSchedulingCapability(): void
    {
        try {
            $scheduledData = new SendMessageData(
                to: '+201234567890',
                message: 'Scheduled test message',
                type: MessageType::SMS,
                scheduledAt: new \DateTime('+1 hour')
            );

            $response = $this->provider->send($scheduledData);

            $this->addTestResult('scheduling_capability', [
                'status' => 'passed',
                'message' => 'Successfully tested scheduling capability',
                'response' => [
                    'message_id' => $response->providerMessageId,
                    'status' => $response->status->value
                ]
            ]);
        } catch (\Exception $e) {
            $this->addTestResult('scheduling_capability', [
                'status' => 'failed',
                'message' => 'Failed to test scheduling capability: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add test result
     */
    private function addTestResult(string $testName, array $result): void
    {
        $this->testResults[$testName] = array_merge($result, [
            'timestamp' => now()->toISOString(),
            'provider' => $this->provider->getName()
        ]);
    }

    /**
     * Get summary of test results
     */
    public function getTestSummary(): array
    {
        $passed = collect($this->testResults)->where('status', 'passed')->count();
        $failed = collect($this->testResults)->where('status', 'failed')->count();
        $total = count($this->testResults);

        return [
            'provider' => $this->provider->getName(),
            'total_tests' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get detailed test results
     */
    public function getDetailedResults(): array
    {
        return [
            'summary' => $this->getTestSummary(),
            'results' => $this->testResults
        ];
    }
}
