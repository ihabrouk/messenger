<?php

namespace App\Messenger\Services;

use App\Messenger\Models\Message;
use App\Messenger\Models\Batch;
use App\Messenger\Models\Template;
use App\Messenger\Models\Consent;
use App\Messenger\Enums\MessageStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get comprehensive analytics data
     */
    public function getAnalytics(
        Carbon $from = null,
        Carbon $to = null,
        string $provider = null,
        string $channel = null
    ): array {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();
        
        $cacheKey = "analytics:" . md5("{$from->timestamp}:{$to->timestamp}:{$provider}:{$channel}");
        
        return Cache::remember($cacheKey, 1800, function () use ($from, $to, $provider, $channel) {
            return [
                'overview' => $this->getOverviewMetrics($from, $to, $provider, $channel),
                'trends' => $this->getTrendData($from, $to, $provider, $channel),
                'providers' => $this->getProviderAnalytics($from, $to, $channel),
                'channels' => $this->getChannelAnalytics($from, $to, $provider),
                'templates' => $this->getTemplateAnalytics($from, $to, $provider, $channel),
                'engagement' => $this->getEngagementMetrics($from, $to, $provider, $channel),
                'cost_analysis' => $this->getCostAnalysis($from, $to, $provider, $channel),
                'consent_metrics' => $this->getConsentMetrics($from, $to, $channel),
            ];
        });
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(Carbon $from, Carbon $to, ?string $provider, ?string $channel): array
    {
        $query = Message::whereBetween('created_at', [$from, $to]);
        
        if ($provider) {
            $query->where('provider', $provider);
        }
        
        if ($channel) {
            $query->where('channel', $channel);
        }

        $totalMessages = $query->count();
        $deliveredMessages = $query->where('status', MessageStatus::DELIVERED)->count();
        $failedMessages = $query->where('status', MessageStatus::FAILED)->count();
        $pendingMessages = $query->whereIn('status', [
            MessageStatus::PENDING,
            MessageStatus::QUEUED,
            MessageStatus::SENDING
        ])->count();

        $totalCost = $query->sum('cost');
        $avgCost = $totalMessages > 0 ? $totalCost / $totalMessages : 0;

        // Calculate delivery time
        $avgDeliveryTime = $query->whereNotNull('delivered_at')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, delivered_at)'));

        return [
            'total_messages' => $totalMessages,
            'delivered_messages' => $deliveredMessages,
            'failed_messages' => $failedMessages,
            'pending_messages' => $pendingMessages,
            'delivery_rate' => $totalMessages > 0 ? ($deliveredMessages / $totalMessages) * 100 : 0,
            'failure_rate' => $totalMessages > 0 ? ($failedMessages / $totalMessages) * 100 : 0,
            'total_cost' => $totalCost,
            'avg_cost_per_message' => $avgCost,
            'avg_delivery_time_seconds' => $avgDeliveryTime,
            'unique_recipients' => $query->distinct('recipient_phone')->count(),
        ];
    }

    /**
     * Get trend data for charts
     */
    private function getTrendData(Carbon $from, Carbon $to, ?string $provider, ?string $channel): array
    {
        $diffInDays = $from->diffInDays($to);
        
        // Determine grouping based on time range
        if ($diffInDays <= 1) {
            $groupBy = 'DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00")';
            $timeFormat = 'hour';
        } elseif ($diffInDays <= 7) {
            $groupBy = 'DATE_FORMAT(created_at, "%Y-%m-%d")';
            $timeFormat = 'day';
        } elseif ($diffInDays <= 90) {
            $groupBy = 'DATE_FORMAT(created_at, "%Y-%u")';
            $timeFormat = 'week';
        } else {
            $groupBy = 'DATE_FORMAT(created_at, "%Y-%m")';
            $timeFormat = 'month';
        }

        $query = Message::selectRaw("
            {$groupBy} as period,
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
            SUM(cost) as cost,
            COUNT(DISTINCT recipient_phone) as unique_recipients
        ", [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
        ->whereBetween('created_at', [$from, $to])
        ->groupBy('period')
        ->orderBy('period');

        if ($provider) {
            $query->where('provider', $provider);
        }
        
        if ($channel) {
            $query->where('channel', $channel);
        }

        $results = $query->get()->map(function ($item) {
            $item->delivery_rate = $item->total > 0 ? ($item->delivered / $item->total) * 100 : 0;
            $item->failure_rate = $item->total > 0 ? ($item->failed / $item->total) * 100 : 0;
            $item->avg_cost = $item->total > 0 ? $item->cost / $item->total : 0;
            return $item;
        });

        return [
            'time_format' => $timeFormat,
            'data' => $results,
        ];
    }

    /**
     * Get provider performance analytics
     */
    private function getProviderAnalytics(Carbon $from, Carbon $to, ?string $channel): array
    {
        $query = Message::selectRaw('
            provider,
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
            SUM(cost) as total_cost,
            AVG(cost) as avg_cost,
            AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, delivered_at) END) as avg_delivery_time,
            COUNT(DISTINCT recipient_phone) as unique_recipients
        ', [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
        ->whereBetween('created_at', [$from, $to])
        ->groupBy('provider')
        ->orderBy('total', 'desc');

        if ($channel) {
            $query->where('channel', $channel);
        }

        return $query->get()->map(function ($provider) {
            $provider->delivery_rate = $provider->total > 0 ? ($provider->delivered / $provider->total) * 100 : 0;
            $provider->failure_rate = $provider->total > 0 ? ($provider->failed / $provider->total) * 100 : 0;
            return $provider;
        })->toArray();
    }

    /**
     * Get channel performance analytics
     */
    private function getChannelAnalytics(Carbon $from, Carbon $to, ?string $provider): array
    {
        $query = Message::selectRaw('
            channel,
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
            SUM(cost) as total_cost,
            AVG(cost) as avg_cost,
            COUNT(DISTINCT recipient_phone) as unique_recipients
        ', [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
        ->whereBetween('created_at', [$from, $to])
        ->groupBy('channel')
        ->orderBy('total', 'desc');

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->get()->map(function ($channel) {
            $channel->delivery_rate = $channel->total > 0 ? ($channel->delivered / $channel->total) * 100 : 0;
            $channel->failure_rate = $channel->total > 0 ? ($channel->failed / $channel->total) * 100 : 0;
            return $channel;
        })->toArray();
    }

    /**
     * Get template performance analytics
     */
    private function getTemplateAnalytics(Carbon $from, Carbon $to, ?string $provider, ?string $channel): array
    {
        $query = Message::join('messenger_templates', 'messenger_messages.template_id', '=', 'messenger_templates.id')
        ->selectRaw('
            messenger_templates.id,
            messenger_templates.name,
            messenger_templates.category,
            COUNT(*) as usage_count,
            SUM(CASE WHEN messenger_messages.status = ? THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN messenger_messages.status = ? THEN 1 ELSE 0 END) as failed,
            SUM(messenger_messages.cost) as total_cost,
            AVG(messenger_messages.cost) as avg_cost
        ', [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
        ->whereBetween('messenger_messages.created_at', [$from, $to])
        ->groupBy('messenger_templates.id', 'messenger_templates.name', 'messenger_templates.category')
        ->orderBy('usage_count', 'desc')
        ->limit(20);

        if ($provider) {
            $query->where('messenger_messages.provider', $provider);
        }
        
        if ($channel) {
            $query->where('messenger_messages.channel', $channel);
        }

        return $query->get()->map(function ($template) {
            $template->delivery_rate = $template->usage_count > 0 ? ($template->delivered / $template->usage_count) * 100 : 0;
            $template->failure_rate = $template->usage_count > 0 ? ($template->failed / $template->usage_count) * 100 : 0;
            return $template;
        })->toArray();
    }

    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics(Carbon $from, Carbon $to, ?string $provider, ?string $channel): array
    {
        $query = Message::whereBetween('created_at', [$from, $to]);
        
        if ($provider) {
            $query->where('provider', $provider);
        }
        
        if ($channel) {
            $query->where('channel', $channel);
        }

        // Message frequency per recipient
        $recipientFrequency = $query->selectRaw('
            recipient_phone,
            COUNT(*) as message_count
        ')
        ->groupBy('recipient_phone')
        ->get();

        $avgMessagesPerRecipient = $recipientFrequency->avg('message_count');
        $maxMessagesPerRecipient = $recipientFrequency->max('message_count');

        // Time-based patterns
        $hourlyPattern = $query->selectRaw('
            HOUR(created_at) as hour,
            COUNT(*) as count
        ')
        ->groupBy('hour')
        ->orderBy('hour')
        ->get()
        ->pluck('count', 'hour')
        ->toArray();

        $dailyPattern = $query->selectRaw('
            DAYOFWEEK(created_at) as day_of_week,
            COUNT(*) as count
        ')
        ->groupBy('day_of_week')
        ->orderBy('day_of_week')
        ->get()
        ->pluck('count', 'day_of_week')
        ->toArray();

        return [
            'avg_messages_per_recipient' => $avgMessagesPerRecipient,
            'max_messages_per_recipient' => $maxMessagesPerRecipient,
            'total_unique_recipients' => $recipientFrequency->count(),
            'hourly_pattern' => $hourlyPattern,
            'daily_pattern' => $dailyPattern,
        ];
    }

    /**
     * Get cost analysis
     */
    private function getCostAnalysis(Carbon $from, Carbon $to, ?string $provider, ?string $channel): array
    {
        $query = Message::whereBetween('created_at', [$from, $to]);
        
        if ($provider) {
            $query->where('provider', $provider);
        }
        
        if ($channel) {
            $query->where('channel', $channel);
        }

        $totalCost = $query->sum('cost');
        $totalMessages = $query->count();
        $avgCostPerMessage = $totalMessages > 0 ? $totalCost / $totalMessages : 0;

        // Cost by status
        $costByStatus = $query->selectRaw('
            status,
            SUM(cost) as total_cost,
            COUNT(*) as count,
            AVG(cost) as avg_cost
        ')
        ->groupBy('status')
        ->get()
        ->keyBy('status')
        ->toArray();

        // Cost efficiency (cost per delivered message)
        $deliveredCost = $costByStatus[MessageStatus::DELIVERED->value]['total_cost'] ?? 0;
        $deliveredCount = $costByStatus[MessageStatus::DELIVERED->value]['count'] ?? 0;
        $costEfficiency = $deliveredCount > 0 ? $deliveredCost / $deliveredCount : 0;

        // Monthly cost trend
        $monthlyCosts = $query->selectRaw('
            DATE_FORMAT(created_at, "%Y-%m") as month,
            SUM(cost) as total_cost,
            COUNT(*) as message_count
        ')
        ->groupBy('month')
        ->orderBy('month')
        ->get()
        ->toArray();

        return [
            'total_cost' => $totalCost,
            'avg_cost_per_message' => $avgCostPerMessage,
            'cost_per_delivered_message' => $costEfficiency,
            'cost_by_status' => $costByStatus,
            'monthly_trend' => $monthlyCosts,
        ];
    }

    /**
     * Get consent metrics
     */
    private function getConsentMetrics(Carbon $from, Carbon $to, ?string $channel): array
    {
        $query = Consent::whereBetween('created_at', [$from, $to]);
        
        if ($channel) {
            $query->where('channel', $channel);
        }

        $totalConsents = $query->count();
        $optedIn = $query->where('status', 'opted_in')->count();
        $optedOut = $query->where('status', 'opted_out')->count();
        $pending = $query->where('status', 'pending')->count();

        $optInRate = $totalConsents > 0 ? ($optedIn / $totalConsents) * 100 : 0;
        $optOutRate = $totalConsents > 0 ? ($optedOut / $totalConsents) * 100 : 0;

        // Consent sources
        $consentSources = $query->selectRaw('
            source,
            COUNT(*) as count
        ')
        ->whereNotNull('source')
        ->groupBy('source')
        ->orderBy('count', 'desc')
        ->get()
        ->pluck('count', 'source')
        ->toArray();

        // Opt-out reasons
        $optOutReasons = Consent::where('status', 'opted_out')
        ->whereNotNull('reason')
        ->whereBetween('opted_out_at', [$from, $to])
        ->selectRaw('
            reason,
            COUNT(*) as count
        ')
        ->groupBy('reason')
        ->orderBy('count', 'desc')
        ->get()
        ->pluck('count', 'reason')
        ->toArray();

        return [
            'total_consents' => $totalConsents,
            'opted_in' => $optedIn,
            'opted_out' => $optedOut,
            'pending' => $pending,
            'opt_in_rate' => $optInRate,
            'opt_out_rate' => $optOutRate,
            'consent_sources' => $consentSources,
            'opt_out_reasons' => $optOutReasons,
        ];
    }

    /**
     * Get real-time metrics
     */
    public function getRealtimeMetrics(): array
    {
        $cacheKey = 'realtime_metrics';
        
        return Cache::remember($cacheKey, 60, function () {
            $now = Carbon::now();
            $lastMinute = $now->copy()->subMinute();
            $lastHour = $now->copy()->subHour();

            return [
                'messages_last_minute' => Message::where('created_at', '>', $lastMinute)->count(),
                'messages_last_hour' => Message::where('created_at', '>', $lastHour)->count(),
                'delivery_rate_last_hour' => $this->getDeliveryRate($lastHour, $now),
                'active_batches' => Batch::where('status', 'processing')->count(),
                'queue_size' => Message::whereIn('status', [
                    MessageStatus::PENDING,
                    MessageStatus::QUEUED
                ])->count(),
                'failed_last_hour' => Message::where('status', MessageStatus::FAILED)
                    ->where('created_at', '>', $lastHour)
                    ->count(),
            ];
        });
    }

    /**
     * Get delivery rate for a time period
     */
    private function getDeliveryRate(Carbon $from, Carbon $to): float
    {
        $total = Message::whereBetween('created_at', [$from, $to])->count();
        
        if ($total === 0) {
            return 0.0;
        }
        
        $delivered = Message::whereBetween('created_at', [$from, $to])
            ->where('status', MessageStatus::DELIVERED)
            ->count();
            
        return ($delivered / $total) * 100;
    }

    /**
     * Export analytics data
     */
    public function exportAnalytics(Carbon $from, Carbon $to, string $format = 'json'): array
    {
        $analytics = $this->getAnalytics($from, $to);
        
        if ($format === 'csv') {
            // Convert to CSV-friendly format
            return $this->convertToCsvFormat($analytics);
        }
        
        return $analytics;
    }

    /**
     * Convert analytics data to CSV format
     */
    private function convertToCsvFormat(array $analytics): array
    {
        // Flatten the nested array structure for CSV export
        $csvData = [];
        
        // Add overview metrics
        foreach ($analytics['overview'] as $key => $value) {
            $csvData[] = ['metric' => $key, 'value' => $value, 'category' => 'overview'];
        }
        
        // Add provider metrics
        foreach ($analytics['providers'] as $provider) {
            foreach ($provider as $key => $value) {
                if ($key !== 'provider') {
                    $csvData[] = [
                        'metric' => $key,
                        'value' => $value,
                        'category' => 'provider',
                        'provider' => $provider['provider']
                    ];
                }
            }
        }
        
        return $csvData;
    }
}
