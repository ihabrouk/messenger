<?php

namespace Ihabrouk\Messenger\Services;

use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Models\Batch;
use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Models\Consent;
use Ihabrouk\Messenger\Enums\MessageStatus;
use Ihabrouk\Messenger\Enums\ConsentStatus;
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
    public function getOverviewMetrics(Carbon $from = null, Carbon $to = null, ?string $provider = null, ?string $channel = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $cacheKey = 'messenger.analytics.overview.' . md5("{$from->timestamp}:{$to->timestamp}:{$provider}:{$channel}");

        return Cache::remember($cacheKey, 1800, function () use ($from, $to, $provider, $channel) {
            $baseQuery = Message::whereBetween('created_at', [$from, $to]);

            if ($provider) {
                $baseQuery->where('provider', $provider);
            }

            if ($channel) {
                $baseQuery->where('channel', $channel);
            }

            $totalMessages = $baseQuery->count();
            $deliveredMessages = (clone $baseQuery)->where('status', MessageStatus::DELIVERED)->count();
            $failedMessages = (clone $baseQuery)->where('status', MessageStatus::FAILED)->count();
            $pendingMessages = (clone $baseQuery)->whereIn('status', [
                MessageStatus::PENDING,
                MessageStatus::QUEUED,
                MessageStatus::SENDING
            ])->count();

        $totalCost = (clone $baseQuery)->sum('cost');
        $avgCost = $totalMessages > 0 ? $totalCost / $totalMessages : 0;

        // Calculate delivery time
        $avgDeliveryTime = (clone $baseQuery)->whereNotNull('delivered_at')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, delivered_at)'));

        return [
            'total_messages' => $totalMessages,
            'delivered_count' => $deliveredMessages,
            'failed_count' => $failedMessages,
            'pending_count' => $pendingMessages,
            'delivery_rate' => $totalMessages > 0 ? ($deliveredMessages / $totalMessages) * 100 : 0,
            'failure_rate' => $totalMessages > 0 ? ($failedMessages / $totalMessages) * 100 : 0,
            'total_cost' => $totalCost,
            'avg_cost_per_message' => $avgCost,
            'avg_delivery_time_seconds' => $avgDeliveryTime,
            'unique_recipients' => (clone $baseQuery)->distinct('to')->count(),
            'active_providers' => (clone $baseQuery)->distinct('provider')->count(),
            'active_channels' => (clone $baseQuery)->distinct('channel')->count(),
        ];
        });
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
            COUNT(DISTINCT `to`) as unique_recipients
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
    public function getProviderAnalytics($from = null, $to = null, ?string $channel = null): array
    {
        // Handle string provider parameter for backward compatibility
        if (is_string($from) && $to === null) {
            $provider = $from;
            $from = Carbon::now()->subDays(30);
            $to = Carbon::now();
            $channel = null;

            // Filter by specific provider
            $query = Message::selectRaw('
                provider,
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(cost) as total_cost,
                AVG(cost) as avg_cost,
                AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, delivered_at) END) as avg_delivery_time,
                COUNT(DISTINCT `to`) as unique_recipients
            ', [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
            ->whereBetween('created_at', [$from, $to])
            ->where('provider', $provider)
            ->groupBy('provider')
            ->first();

            if (!$query) {
                return [
                    'total_messages' => 0,
                    'delivery_rate' => 0,
                    'error_rate' => 0,
                    'avg_response_time' => 0,
                    'total_cost' => 0,
                    'avg_cost' => 0,
                    'unique_recipients' => 0,
                    'channel_breakdown' => [],
                ];
            }

            return [
                'total_messages' => $query->total,
                'delivery_rate' => $query->total > 0 ? ($query->delivered / $query->total) * 100 : 0,
                'error_rate' => $query->total > 0 ? ($query->failed / $query->total) * 100 : 0,
                'avg_response_time' => $query->avg_delivery_time,
                'total_cost' => $query->total_cost,
                'avg_cost' => $query->avg_cost,
                'unique_recipients' => $query->unique_recipients,
                'channel_breakdown' => [], // Empty for single provider query
            ];
        }

        // Handle Carbon date parameters
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();
        $query = Message::selectRaw('
            provider,
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
            SUM(cost) as total_cost,
            AVG(cost) as avg_cost,
            AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, delivered_at) END) as avg_delivery_time,
            COUNT(DISTINCT `to`) as unique_recipients
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
            COUNT(DISTINCT `to`) as unique_recipients
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
    public function getTemplateAnalytics(Carbon $from = null, Carbon $to = null, ?string $provider = null, ?string $channel = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();
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
            $template->template_name = $template->name; // Add expected field name
            return $template;
        })->toArray();
    }

    /**
     * Get engagement metrics
     */
    public function getEngagementMetrics(Carbon $from = null, Carbon $to = null, ?string $provider = null, ?string $channel = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();
        $query = Message::whereBetween('created_at', [$from, $to]);

        if ($provider) {
            $query->where('provider', $provider);
        }

        if ($channel) {
            $query->where('channel', $channel);
        }

        // Message frequency per recipient
        $recipientFrequency = $query->selectRaw('
            `to`,
            COUNT(*) as message_count
        ')
        ->groupBy('to')
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

        $totalRecipients = $recipientFrequency->count();
        $repeatRecipients = $recipientFrequency->where('message_count', '>', 1)->count();
        $engagementRate = $totalRecipients > 0 ? ($repeatRecipients / $totalRecipients) * 100 : 0;

        return [
            'total_recipients' => $totalRecipients,
            'unique_recipients' => $totalRecipients,
            'repeat_recipients' => $repeatRecipients,
            'avg_messages_per_recipient' => $avgMessagesPerRecipient,
            'engagement_rate' => $engagementRate,
            'max_messages_per_recipient' => $maxMessagesPerRecipient,
            'hourly_pattern' => $hourlyPattern,
            'daily_pattern' => $dailyPattern,
        ];
    }

    /**
     * Get cost analysis
     */
    public function getCostAnalysis($from = null, $to = null, ?string $provider = null, ?string $channel = null): array
    {
        // Handle string period parameter (e.g., "2024-07")
        if (is_string($from) && $to === null) {
            $period = $from;
            if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $from = Carbon::create($year, $month, 1)->startOfMonth();
                $to = Carbon::create($year, $month, 1)->endOfMonth();
                $provider = null;
                $channel = null;
            } else {
                // Default to current month
                $from = Carbon::now()->startOfMonth();
                $to = Carbon::now()->endOfMonth();
            }
        } else {
            // Handle Carbon date parameters
            $from = $from ?? Carbon::now()->subDays(30);
            $to = $to ?? Carbon::now();
        }
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

        // Cost by provider
        $costByProvider = $query->selectRaw('
            provider,
            SUM(cost) as total_cost,
            COUNT(*) as message_count,
            AVG(cost) as avg_cost
        ')
        ->groupBy('provider')
        ->get()
        ->keyBy('provider')
        ->toArray();

        // Cost by channel
        $costByChannel = $query->selectRaw('
            channel,
            SUM(cost) as total_cost,
            COUNT(*) as message_count,
            AVG(cost) as avg_cost
        ')
        ->groupBy('channel')
        ->get()
        ->keyBy('channel')
        ->toArray();

        // Daily costs
        $dailyCosts = $query->selectRaw('
            DATE(created_at) as date,
            SUM(cost) as total_cost,
            COUNT(*) as message_count
        ')
        ->groupBy('date')
        ->orderBy('date')
        ->get()
        ->toArray();

        return [
            'total_cost' => $totalCost,
            'avg_cost_per_message' => $avgCostPerMessage,
            'cost_per_delivered_message' => $costEfficiency,
            'cost_by_status' => $costByStatus,
            'cost_by_provider' => $costByProvider,
            'cost_by_channel' => $costByChannel,
            'monthly_trend' => $monthlyCosts,
            'daily_costs' => $dailyCosts,
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
        $grantedConsents = $query->where('status', ConsentStatus::GRANTED->value)->count();
        $revokedConsents = $query->where('status', ConsentStatus::REVOKED->value)->count();
        $pendingConsents = $query->where('status', ConsentStatus::PENDING->value)->count();

        $optInRate = $totalConsents > 0 ? ($grantedConsents / $totalConsents) * 100 : 0;
        $optOutRate = $totalConsents > 0 ? ($revokedConsents / $totalConsents) * 100 : 0;

        // Consent by type
        $consentByType = $query->selectRaw('
            type,
            COUNT(*) as count
        ')
        ->groupBy('type')
        ->orderBy('count', 'desc')
        ->get()
        ->pluck('count', 'type')
        ->toArray();

        // Recent revoked consents
        $recentRevokedConsents = Consent::where('status', ConsentStatus::REVOKED->value)
        ->whereNotNull('revoked_at')
        ->whereBetween('revoked_at', [$from, $to])
        ->count();

        return [
            'total_consents' => $totalConsents,
            'granted_consents' => $grantedConsents,
            'revoked_consents' => $revokedConsents,
            'pending_consents' => $pendingConsents,
            'opt_in_rate' => $optInRate,
            'opt_out_rate' => $optOutRate,
            'consent_by_type' => $consentByType,
            'recent_revoked' => $recentRevokedConsents,
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

    /**
     * Get trend analysis for a given period
     */
    public function getTrendAnalysis(Carbon $from, Carbon $to, string $granularity = 'daily'): array
    {
        $trendData = $this->getTrendData($from, $to, null, null);
        return $trendData['data']->toArray();
    }

    /**
     * Get consent analytics
     */
    public function getConsentAnalytics(): array
    {
        $totalConsents = Consent::count();
        $grantedConsents = Consent::where('status', ConsentStatus::GRANTED)->count();
        $revokedConsents = Consent::where('status', ConsentStatus::REVOKED)->count();
        $pendingConsents = Consent::where('status', ConsentStatus::PENDING)->count();

        // Get consent breakdown by type
        $consentByType = Consent::selectRaw('type, status, COUNT(*) as count')
            ->groupBy('type', 'status')
            ->get()
            ->groupBy('type')
            ->map(function ($typeConsents) {
                $result = [];
                foreach ($typeConsents as $consent) {
                    $result[$consent->status->value ?? $consent->status] = $consent->count;
                }
                return $result;
            })
            ->toArray();

        return [
            'total_consents' => $totalConsents,
            'granted_consents' => $grantedConsents,
            'revoked_consents' => $revokedConsents,
            'pending_consents' => $pendingConsents,
            'consent_rate' => $totalConsents > 0 ? ($grantedConsents / $totalConsents) * 100 : 0,
            'consent_by_type' => $consentByType,
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(Carbon $from = null, Carbon $to = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        $query = Message::whereBetween('created_at', [$from, $to]);

        $avgDeliveryTime = $query->whereNotNull('delivered_at')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, delivered_at)'));

        $totalMessages = $query->count();
        $failedMessages = $query->where('status', MessageStatus::FAILED)->count();

        $messagesPerHour = $totalMessages / max(1, $from->diffInHours($to));

        return [
            'avg_delivery_time' => $avgDeliveryTime,
            'throughput' => $messagesPerHour,
            'error_rate' => $totalMessages > 0 ? ($failedMessages / $totalMessages) * 100 : 0,
            'cost_efficiency' => $query->avg('cost'),
        ];
    }
}
