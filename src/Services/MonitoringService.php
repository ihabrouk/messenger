<?php

namespace App\Messenger\Services;

use App\Messenger\Models\Message;
use App\Messenger\Models\BulkMessage;
use App\Messenger\Enums\MessageStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MonitoringService
{
    public function getSystemHealth(): array
    {
        return [
            'queue_status' => $this->getQueueStatus(),
            'provider_health' => $this->getProviderHealth(),
            'recent_errors' => $this->getRecentErrors(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'circuit_breaker_status' => $this->getCircuitBreakerStatus(),
        ];
    }

    public function getMetrics(Carbon $from = null, Carbon $to = null): array
    {
        $from = $from ?? Carbon::now()->subDays(7);
        $to = $to ?? Carbon::now();

        return [
            'overview' => $this->getOverviewMetrics($from, $to),
            'provider_breakdown' => $this->getProviderBreakdown($from, $to),
            'template_performance' => $this->getTemplatePerformance($from, $to),
            'delivery_times' => $this->getDeliveryTimes($from, $to),
            'error_analysis' => $this->getErrorAnalysis($from, $to),
        ];
    }

    protected function getQueueStatus(): array
    {
        $status = [
            'pending_messages' => Message::where('status', MessageStatus::PENDING)->count(),
            'processing_messages' => Message::where('status', MessageStatus::PROCESSING)->count(),
            'failed_messages' => Message::where('status', MessageStatus::FAILED)
                ->where('created_at', '>', Carbon::now()->subHour())
                ->count(),
        ];

        $status['health'] = $this->determineQueueHealth($status);

        return $status;
    }

    protected function getProviderHealth(): array
    {
        $providers = ['sms_misr', 'twilio', 'mock_test'];
        $health = [];

        foreach ($providers as $provider) {
            $recent_messages = Message::where('provider', $provider)
                ->where('created_at', '>', Carbon::now()->subHour())
                ->get();

            $total = $recent_messages->count();
            $failed = $recent_messages->where('status', MessageStatus::FAILED)->count();
            $success_rate = $total > 0 ? (($total - $failed) / $total) * 100 : 100;

            $health[$provider] = [
                'success_rate' => round($success_rate, 2),
                'total_messages' => $total,
                'failed_messages' => $failed,
                'status' => $this->determineProviderStatus($success_rate),
                'circuit_breaker' => app(CircuitBreakerService::class)->getState($provider),
            ];
        }

        return $health;
    }

    protected function getRecentErrors(int $limit = 10): array
    {
        return Message::where('status', MessageStatus::FAILED)
            ->where('created_at', '>', Carbon::now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'provider', 'error_message', 'created_at'])
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'provider' => $message->provider,
                    'error' => $message->error_message,
                    'time' => $message->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    protected function getPerformanceMetrics(): array
    {
        $cacheKey = 'messenger_performance_metrics';

        return Cache::remember($cacheKey, 300, function () {
            $now = Carbon::now();
            $lastHour = $now->copy()->subHour();

            return [
                'messages_per_hour' => Message::where('created_at', '>', $lastHour)->count(),
                'avg_delivery_time' => $this->getAverageDeliveryTime($lastHour, $now),
                'success_rate' => $this->getSuccessRate($lastHour, $now),
                'active_campaigns' => BulkMessage::where('status', 'processing')->count(),
            ];
        });
    }

    protected function getCircuitBreakerStatus(): array
    {
        $circuitBreakerService = app(CircuitBreakerService::class);
        $providers = ['sms_misr', 'twilio', 'mock_test'];
        $status = [];

        foreach ($providers as $provider) {
            $state = $circuitBreakerService->getState($provider);
            $status[$provider] = [
                'state' => $state,
                'failure_count' => $circuitBreakerService->getFailureCount($provider),
                'last_failure' => $circuitBreakerService->getLastFailureTime($provider),
                'next_retry' => $state === 'open' ? $circuitBreakerService->getNextRetryTime($provider) : null,
            ];
        }

        return $status;
    }

    protected function getOverviewMetrics(Carbon $from, Carbon $to): array
    {
        $messages = Message::whereBetween('created_at', [$from, $to]);

        return [
            'total_messages' => $messages->count(),
            'delivered_messages' => $messages->where('status', MessageStatus::DELIVERED)->count(),
            'failed_messages' => $messages->where('status', MessageStatus::FAILED)->count(),
            'pending_messages' => $messages->where('status', MessageStatus::PENDING)->count(),
            'success_rate' => $this->calculateSuccessRate($messages),
            'total_cost' => $messages->sum('cost'),
        ];
    }

    protected function getProviderBreakdown(Carbon $from, Carbon $to): array
    {
        return Message::whereBetween('created_at', [$from, $to])
            ->groupBy('provider')
            ->select([
                'provider',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                DB::raw('SUM(cost) as total_cost'),
                DB::raw('AVG(cost) as avg_cost'),
            ])
            ->get()
            ->mapWithKeys(function ($row) {
                $success_rate = $row->total > 0 ? ($row->delivered / $row->total) * 100 : 0;

                return [$row->provider => [
                    'total' => $row->total,
                    'delivered' => $row->delivered,
                    'failed' => $row->failed,
                    'success_rate' => round($success_rate, 2),
                    'total_cost' => $row->total_cost,
                    'avg_cost' => round($row->avg_cost, 4),
                ]];
            })
            ->toArray();
    }

    protected function getTemplatePerformance(Carbon $from, Carbon $to): array
    {
        return Message::whereBetween('created_at', [$from, $to])
            ->whereNotNull('template_id')
            ->join('message_templates', 'messages.template_id', '=', 'message_templates.id')
            ->groupBy('template_id', 'message_templates.name')
            ->select([
                'template_id',
                'message_templates.name as template_name',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('AVG(cost) as avg_cost'),
            ])
            ->orderBy('usage_count', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $success_rate = $row->usage_count > 0 ? ($row->delivered / $row->usage_count) * 100 : 0;

                return [
                    'template_id' => $row->template_id,
                    'template_name' => $row->template_name,
                    'usage_count' => $row->usage_count,
                    'delivered' => $row->delivered,
                    'success_rate' => round($success_rate, 2),
                    'avg_cost' => round($row->avg_cost, 4),
                ];
            })
            ->toArray();
    }

    protected function getDeliveryTimes(Carbon $from, Carbon $to): array
    {
        $deliveryTimes = Message::whereBetween('created_at', [$from, $to])
            ->where('status', MessageStatus::DELIVERED)
            ->whereNotNull('delivered_at')
            ->select([
                DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as avg_seconds'),
                DB::raw('MIN(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as min_seconds'),
                DB::raw('MAX(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as max_seconds'),
                'provider'
            ])
            ->groupBy('provider')
            ->get();

        return $deliveryTimes->mapWithKeys(function ($row) {
            return [$row->provider => [
                'avg_delivery_time' => round($row->avg_seconds, 2),
                'min_delivery_time' => $row->min_seconds,
                'max_delivery_time' => $row->max_seconds,
            ]];
        })->toArray();
    }

    protected function getErrorAnalysis(Carbon $from, Carbon $to): array
    {
        $errors = Message::whereBetween('created_at', [$from, $to])
            ->where('status', MessageStatus::FAILED)
            ->whereNotNull('error_message')
            ->select([
                'error_message',
                'provider',
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('error_message', 'provider')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get();

        return $errors->map(function ($error) {
            return [
                'error_message' => $error->error_message,
                'provider' => $error->provider,
                'count' => $error->count,
            ];
        })->toArray();
    }

    protected function determineQueueHealth(array $status): string
    {
        if ($status['failed_messages'] > 100) {
            return 'critical';
        }

        if ($status['pending_messages'] > 1000 || $status['failed_messages'] > 10) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function determineProviderStatus(float $successRate): string
    {
        if ($successRate < 80) {
            return 'critical';
        }

        if ($successRate < 95) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function getAverageDeliveryTime(Carbon $from, Carbon $to): ?float
    {
        $avgSeconds = Message::whereBetween('created_at', [$from, $to])
            ->where('status', MessageStatus::DELIVERED)
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as avg_seconds')
            ->value('avg_seconds');

        return $avgSeconds ? round($avgSeconds, 2) : null;
    }

    protected function getSuccessRate(Carbon $from, Carbon $to): float
    {
        $total = Message::whereBetween('created_at', [$from, $to])->count();

        if ($total === 0) {
            return 100.0;
        }

        $delivered = Message::whereBetween('created_at', [$from, $to])
            ->where('status', MessageStatus::DELIVERED)
            ->count();

        return round(($delivered / $total) * 100, 2);
    }

    protected function calculateSuccessRate($query): float
    {
        $total = $query->count();

        if ($total === 0) {
            return 100.0;
        }

        $delivered = $query->where('status', MessageStatus::DELIVERED)->count();

        return round(($delivered / $total) * 100, 2);
    }

    public function getRealtimeStats(): array
    {
        return Cache::remember('messenger_realtime_stats', 60, function () {
            $now = Carbon::now();
            $lastMinute = $now->copy()->subMinute();

            return [
                'messages_last_minute' => Message::where('created_at', '>', $lastMinute)->count(),
                'active_queues' => $this->getActiveQueueCount(),
                'current_throughput' => $this->getCurrentThroughput(),
                'system_status' => $this->getSystemStatus(),
            ];
        });
    }

    protected function getActiveQueueCount(): int
    {
        return Message::whereIn('status', [MessageStatus::PENDING, MessageStatus::PROCESSING])->count();
    }

    protected function getCurrentThroughput(): int
    {
        $lastMinute = Carbon::now()->subMinute();
        return Message::where('status', MessageStatus::DELIVERED)
            ->where('delivered_at', '>', $lastMinute)
            ->count();
    }

    protected function getSystemStatus(): string
    {
        $health = $this->getSystemHealth();

        if ($health['queue_status']['health'] === 'critical') {
            return 'critical';
        }

        $criticalProviders = collect($health['provider_health'])
            ->where('status', 'critical')
            ->count();

        if ($criticalProviders > 1) {
            return 'critical';
        }

        if ($criticalProviders === 1 || $health['queue_status']['health'] === 'warning') {
            return 'warning';
        }

        return 'healthy';
    }
}
