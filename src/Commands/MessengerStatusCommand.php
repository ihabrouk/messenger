<?php

namespace Ihabrouk\Messenger\Commands;

use Exception;
use Ihabrouk\Messenger\Services\MonitoringService;
use Illuminate\Console\Command;

class MessengerStatusCommand extends Command
{
    protected $signature = 'messenger:status
                           {--json : Output as JSON}
                           {--provider= : Check specific provider status}
                           {--realtime : Show realtime stats}';

    protected $description = 'Show messenger system status and health';

    protected MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        parent::__construct();
        $this->monitoringService = $monitoringService;
    }

    public function handle(): int
    {
        try {
            if ($this->option('realtime')) {
                return $this->showRealtimeStats();
            }

            if ($this->option('provider')) {
                return $this->showProviderStatus($this->option('provider'));
            }

            return $this->showSystemHealth();

        } catch (Exception $e) {
            $this->error("Failed to get status: " . $e->getMessage());
            return 1;
        }
    }

    protected function showSystemHealth(): int
    {
        $health = $this->monitoringService->getSystemHealth();

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('📊 Messenger System Health');
        $this->line('');

        // Queue Status
        $queue = $health['queue_status'];
        $queueIcon = $this->getHealthIcon($queue['health']);
        $this->line("🔄 Queue Status: {$queueIcon} " . ucfirst($queue['health']));
        $this->line("   Pending: {$queue['pending_messages']}");
        $this->line("   Processing: {$queue['processing_messages']}");
        $this->line("   Failed (last hour): {$queue['failed_messages']}");
        $this->line('');

        // Provider Health
        $this->line('📡 Provider Health:');
        foreach ($health['provider_health'] as $provider => $stats) {
            $icon = $this->getHealthIcon($stats['status']);
            $circuitIcon = $this->getCircuitIcon($stats['circuit_breaker']);

            $this->line("   {$provider}: {$icon} {$stats['success_rate']}% success {$circuitIcon}");
            $this->line("      Total: {$stats['total_messages']}, Failed: {$stats['failed_messages']}");
        }
        $this->line('');

        // Performance Metrics
        $perf = $health['performance_metrics'];
        $this->line('⚡ Performance:');
        $this->line("   Messages/hour: {$perf['messages_per_hour']}");
        $this->line("   Avg delivery time: " . ($perf['avg_delivery_time'] ? $perf['avg_delivery_time'] . 's' : 'N/A'));
        $this->line("   Success rate: {$perf['success_rate']}%");
        $this->line("   Active campaigns: {$perf['active_campaigns']}");
        $this->line('');

        // Recent Errors
        if (!empty($health['recent_errors'])) {
            $this->line('❌ Recent Errors:');
            foreach (array_slice($health['recent_errors'], 0, 5) as $error) {
                $this->line("   [{$error['provider']}] {$error['error']} ({$error['time']})");
            }
        }

        return 0;
    }

    protected function showProviderStatus(string $provider): int
    {
        $health = $this->monitoringService->getProviderHealth();

        if (!isset($health[$provider])) {
            $this->error("Provider '{$provider}' not found");
            return 1;
        }

        $stats = $health[$provider];

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return 0;
        }

        $icon = $this->getHealthIcon($stats['status']);
        $circuitIcon = $this->getCircuitIcon($stats['circuit_breaker']);

        $this->info("📡 Provider: {$provider}");
        $this->line("Status: {$icon} " . ucfirst($stats['status']));
        $this->line("Success Rate: {$stats['success_rate']}%");
        $this->line("Total Messages: {$stats['total_messages']}");
        $this->line("Failed Messages: {$stats['failed_messages']}");
        $this->line("Circuit Breaker: {$circuitIcon} " . ucfirst($stats['circuit_breaker']));

        return 0;
    }

    protected function showRealtimeStats(): int
    {
        $stats = $this->monitoringService->getRealtimeStats();

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return 0;
        }

        $statusIcon = $this->getSystemStatusIcon($stats['system_status']);

        $this->info('🔴 Realtime Stats');
        $this->line("System Status: {$statusIcon} " . ucfirst($stats['system_status']));
        $this->line("Messages (last minute): {$stats['messages_last_minute']}");
        $this->line("Active Queues: {$stats['active_queues']}");
        $this->line("Current Throughput: {$stats['current_throughput']}/min");
        $this->line("Timestamp: " . now()->format('Y-m-d H:i:s'));

        return 0;
    }

    protected function getHealthIcon(string $health): string
    {
        return match ($health) {
            'healthy' => '🟢',
            'warning' => '🟡',
            'critical' => '🔴',
            default => '⚪'
        };
    }

    protected function getCircuitIcon(string $state): string
    {
        return match ($state) {
            'closed' => '🟢',
            'open' => '🔴',
            'half-open' => '🟡',
            default => '⚪'
        };
    }

    protected function getSystemStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy' => '🟢',
            'warning' => '🟡',
            'critical' => '🔴',
            default => '⚪'
        };
    }
}
