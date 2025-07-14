<?php

namespace App\Messenger\Services;

use App\Messenger\Models\Message;
use App\Messenger\Models\BulkMessage;
use App\Messenger\Jobs\SendBulkMessageJob;
use App\Messenger\Jobs\ProcessScheduledMessageJob;
use App\Messenger\Jobs\RetryFailedMessageJob;
use App\Messenger\Enums\MessageStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class AutomationService
{
    protected MonitoringService $monitoringService;
    protected CircuitBreakerService $circuitBreakerService;

    public function __construct(
        MonitoringService $monitoringService,
        CircuitBreakerService $circuitBreakerService
    ) {
        $this->monitoringService = $monitoringService;
        $this->circuitBreakerService = $circuitBreakerService;
    }

    /**
     * Process scheduled messages that are due
     */
    public function processScheduledMessages(): int
    {
        $dueMessages = Message::where('status', MessageStatus::SCHEDULED)
            ->where('scheduled_at', '<=', Carbon::now())
            ->limit(100)
            ->get();

        $processed = 0;

        foreach ($dueMessages as $message) {
            try {
                ProcessScheduledMessageJob::dispatch($message->id)
                    ->onQueue('messages');

                $message->update(['status' => MessageStatus::PENDING]);
                $processed++;

                Log::info('Scheduled message queued for processing', ['message_id' => $message->id]);
            } catch (\Exception $e) {
                Log::error('Failed to queue scheduled message', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($processed > 0) {
            Log::info("Processed {$processed} scheduled messages");
        }

        return $processed;
    }

    /**
     * Process bulk message campaigns
     */
    public function processBulkCampaigns(): int
    {
        $pendingCampaigns = BulkMessage::where('status', 'pending')
            ->where('scheduled_at', '<=', Carbon::now())
            ->limit(10)
            ->get();

        $processed = 0;

        foreach ($pendingCampaigns as $campaign) {
            try {
                SendBulkMessageJob::dispatch($campaign->id)
                    ->onQueue('bulk');

                $campaign->update(['status' => 'processing']);
                $processed++;

                Log::info('Bulk campaign queued for processing', ['campaign_id' => $campaign->id]);
            } catch (\Exception $e) {
                Log::error('Failed to queue bulk campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($processed > 0) {
            Log::info("Processed {$processed} bulk campaigns");
        }

        return $processed;
    }

    /**
     * Auto-retry failed messages based on intelligent rules
     */
    public function autoRetryFailedMessages(): int
    {
        $retryableMessages = Message::where('status', MessageStatus::FAILED)
            ->where('retry_count', '<', config('messenger.max_retries', 3))
            ->where('created_at', '>', Carbon::now()->subHours(24))
            ->whereNotNull('error_message')
            ->get();

        $retried = 0;

        foreach ($retryableMessages as $message) {
            if ($this->shouldRetryMessage($message)) {
                $delay = $this->calculateRetryDelay($message);

                try {
                    RetryFailedMessageJob::dispatch($message->id)
                        ->delay($delay)
                        ->onQueue('retries');

                    $retried++;

                    Log::info('Failed message queued for retry', [
                        'message_id' => $message->id,
                        'retry_count' => $message->retry_count + 1,
                        'delay_minutes' => $delay->totalMinutes
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to queue message retry', [
                        'message_id' => $message->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if ($retried > 0) {
            Log::info("Queued {$retried} messages for retry");
        }

        return $retried;
    }

    /**
     * Clean up old completed messages and logs
     */
    public function cleanupOldData(): array
    {
        $cutoffDate = Carbon::now()->subDays(config('messenger.cleanup_days', 30));

        $deletedMessages = Message::where('status', MessageStatus::DELIVERED)
            ->where('delivered_at', '<', $cutoffDate)
            ->delete();

        $deletedLogs = \App\Messenger\Models\Log::where('created_at', '<', $cutoffDate)
            ->delete();

        Log::info('Cleanup completed', [
            'deleted_messages' => $deletedMessages,
            'deleted_logs' => $deletedLogs,
            'cutoff_date' => $cutoffDate->toDateString()
        ]);

        return [
            'deleted_messages' => $deletedMessages,
            'deleted_logs' => $deletedLogs,
            'cutoff_date' => $cutoffDate
        ];
    }

    /**
     * Monitor and heal circuit breakers
     */
    public function healCircuitBreakers(): int
    {
        $providers = ['sms_misr', 'twilio', 'mock_test'];
        $healed = 0;

        foreach ($providers as $provider) {
            $state = $this->circuitBreakerService->getState($provider);

            if ($state === 'half-open') {
                // Check if provider is healthy enough to close circuit
                if ($this->isProviderHealthy($provider)) {
                    $this->circuitBreakerService->recordSuccess($provider);
                    $healed++;

                    Log::info('Circuit breaker healed', ['provider' => $provider]);
                }
            } elseif ($state === 'open') {
                // Try to transition to half-open if timeout has passed
                if ($this->circuitBreakerService->canAttemptReset($provider)) {
                    $this->circuitBreakerService->attemptReset($provider);

                    Log::info('Circuit breaker reset attempted', ['provider' => $provider]);
                }
            }
        }

        return $healed;
    }

    /**
     * Balance load across providers
     */
    public function balanceProviderLoad(): array
    {
        $health = $this->monitoringService->getProviderHealth();
        $recommendations = [];

        foreach ($health as $provider => $stats) {
            if ($stats['status'] === 'critical' && $stats['circuit_breaker'] === 'closed') {
                // Suggest opening circuit breaker
                $recommendations[] = [
                    'action' => 'open_circuit',
                    'provider' => $provider,
                    'reason' => 'Poor performance detected',
                    'success_rate' => $stats['success_rate']
                ];
            } elseif ($stats['status'] === 'healthy' && $stats['circuit_breaker'] === 'open') {
                // Suggest closing circuit breaker
                $recommendations[] = [
                    'action' => 'close_circuit',
                    'provider' => $provider,
                    'reason' => 'Provider performance improved',
                    'success_rate' => $stats['success_rate']
                ];
            }
        }

        // Auto-apply safe recommendations
        foreach ($recommendations as $recommendation) {
            if ($this->canAutoApplyRecommendation($recommendation)) {
                $this->applyRecommendation($recommendation);
            }
        }

        return $recommendations;
    }

    /**
     * Generate health alerts
     */
    public function generateHealthAlerts(): array
    {
        $health = $this->monitoringService->getSystemHealth();
        $alerts = [];

        // Queue health alerts
        if ($health['queue_status']['health'] === 'critical') {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'queue',
                'message' => 'Queue health is critical - high failure rate detected',
                'data' => $health['queue_status']
            ];
        }

        // Provider health alerts
        foreach ($health['provider_health'] as $provider => $stats) {
            if ($stats['status'] === 'critical') {
                $alerts[] = [
                    'type' => 'critical',
                    'category' => 'provider',
                    'message' => "Provider {$provider} is experiencing critical issues",
                    'data' => $stats
                ];
            }
        }

        // Performance alerts
        $metrics = $health['performance_metrics'];
        if ($metrics['success_rate'] < 90) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'performance',
                'message' => 'Overall success rate below threshold',
                'data' => ['success_rate' => $metrics['success_rate']]
            ];
        }

        // Log all alerts
        foreach ($alerts as $alert) {
            Log::channel('messenger')->warning('Health alert generated', $alert);
        }

        return $alerts;
    }

    protected function shouldRetryMessage(Message $message): bool
    {
        // Don't retry if provider circuit breaker is open
        if ($this->circuitBreakerService->getState($message->provider) === 'open') {
            return false;
        }

        // Check if error is retryable
        $retryableErrors = [
            'timeout',
            'network_error',
            'rate_limit',
            'temporary_failure',
            'service_unavailable'
        ];

        $errorMessage = strtolower($message->error_message ?? '');

        foreach ($retryableErrors as $retryableError) {
            if (str_contains($errorMessage, $retryableError)) {
                return true;
            }
        }

        return false;
    }

    protected function calculateRetryDelay(Message $message): \DateInterval
    {
        // Exponential backoff: 2^retry_count minutes
        $minutes = pow(2, $message->retry_count) * 5; // 5, 10, 20, 40 minutes

        // Cap at 2 hours
        $minutes = min($minutes, 120);

        return \DateInterval::createFromDateString("{$minutes} minutes");
    }

    protected function isProviderHealthy(string $provider): bool
    {
        $recentMessages = Message::where('provider', $provider)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->get();

        if ($recentMessages->count() < 5) {
            return true; // Not enough data, assume healthy
        }

        $failureRate = $recentMessages->where('status', MessageStatus::FAILED)->count() / $recentMessages->count();

        return $failureRate < 0.1; // Less than 10% failure rate
    }

    protected function canAutoApplyRecommendation(array $recommendation): bool
    {
        // Only auto-apply safe recommendations
        if ($recommendation['action'] === 'open_circuit' && $recommendation['success_rate'] < 50) {
            return true;
        }

        if ($recommendation['action'] === 'close_circuit' && $recommendation['success_rate'] > 95) {
            return true;
        }

        return false;
    }

    protected function applyRecommendation(array $recommendation): void
    {
        $provider = $recommendation['provider'];

        switch ($recommendation['action']) {
            case 'open_circuit':
                $this->circuitBreakerService->recordFailure($provider);
                Log::info('Auto-opened circuit breaker', $recommendation);
                break;

            case 'close_circuit':
                $this->circuitBreakerService->recordSuccess($provider);
                Log::info('Auto-closed circuit breaker', $recommendation);
                break;
        }
    }

    /**
     * Run all automation tasks
     */
    public function runAll(): array
    {
        $results = [];

        try {
            $results['scheduled_messages'] = $this->processScheduledMessages();
            $results['bulk_campaigns'] = $this->processBulkCampaigns();
            $results['retried_messages'] = $this->autoRetryFailedMessages();
            $results['healed_circuits'] = $this->healCircuitBreakers();
            $results['load_balance'] = $this->balanceProviderLoad();
            $results['alerts'] = $this->generateHealthAlerts();

            Log::info('Automation tasks completed', $results);
        } catch (\Exception $e) {
            Log::error('Automation task failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }
}
