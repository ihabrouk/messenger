<?php

namespace Ihabrouk\Messenger\Livewire;

use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Enums\MessageStatus;
use Ihabrouk\Messenger\Services\MonitoringService;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MessengerAnalyticsDashboard extends Component
{
    public $timeRange = '24h';
    public $selectedProvider = 'all';
    public $selectedChannel = 'all';
    public $autoRefresh = true;

    protected $queryString = [
        'timeRange' => ['except' => '24h'],
        'selectedProvider' => ['except' => 'all'],
        'selectedChannel' => ['except' => 'all'],
    ];

    public function mount()
    {
        // Initialize component
    }

    public function updatedTimeRange()
    {
        $this->dispatch('refresh-charts');
    }

    public function updatedSelectedProvider()
    {
        $this->dispatch('refresh-charts');
    }

    public function updatedSelectedChannel()
    {
        $this->dispatch('refresh-charts');
    }

    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
        $this->dispatch('toggle-auto-refresh', ['enabled' => $this->autoRefresh]);
    }

    public function refreshData()
    {
        $this->dispatch('refresh-charts');
        $this->dispatch('data-refreshed');
    }

    public function getMetricsProperty()
    {
        $cacheKey = "messenger_metrics_{$this->timeRange}_{$this->selectedProvider}_{$this->selectedChannel}";

        return Cache::remember($cacheKey, 300, function () {
            $timeWindow = $this->getTimeWindow();

            $baseQuery = Message::where('created_at', '>=', $timeWindow);

            if ($this->selectedProvider !== 'all') {
                $baseQuery->where('provider', $this->selectedProvider);
            }

            if ($this->selectedChannel !== 'all') {
                $baseQuery->where('channel', $this->selectedChannel);
            }

            $total = $baseQuery->count();
            $delivered = (clone $baseQuery)->where('status', MessageStatus::DELIVERED)->count();
            $failed = (clone $baseQuery)->where('status', MessageStatus::FAILED)->count();
            $pending = (clone $baseQuery)->whereIn('status', [
                MessageStatus::PENDING,
                MessageStatus::QUEUED,
                MessageStatus::SENDING
            ])->count();

            $totalCost = (clone $baseQuery)->sum('cost');
            $avgDeliveryTime = $this->calculateAvgDeliveryTime(clone $baseQuery);

            return [
                'total_messages' => $total,
                'delivered_count' => $delivered,
                'failed_count' => $failed,
                'pending_count' => $pending,
                'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
                'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
                'total_cost' => $totalCost,
                'avg_delivery_time' => $avgDeliveryTime,
                'avg_cost_per_message' => $total > 0 ? round($totalCost / $total, 4) : 0,
            ];
        });
    }

    public function getHourlyDataProperty()
    {
        $cacheKey = "messenger_hourly_{$this->timeRange}_{$this->selectedProvider}_{$this->selectedChannel}";

        return Cache::remember($cacheKey, 300, function () {
            $timeWindow = $this->getTimeWindow();

            $query = Message::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(cost) as cost
            ', [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
            ->where('created_at', '>=', $timeWindow)
            ->groupBy('hour')
            ->orderBy('hour');

            if ($this->selectedProvider !== 'all') {
                $query->where('provider', $this->selectedProvider);
            }

            if ($this->selectedChannel !== 'all') {
                $query->where('channel', $this->selectedChannel);
            }

            return $query->get()->map(function ($row) {
                $row->delivery_rate = $row->total > 0 ? round(($row->delivered / $row->total) * 100, 2) : 0;
                return $row;
            });
        });
    }

    public function getProviderStatsProperty()
    {
        $cacheKey = "messenger_provider_stats_{$this->timeRange}_{$this->selectedChannel}";

        return Cache::remember($cacheKey, 300, function () {
            $timeWindow = $this->getTimeWindow();

            $query = Message::selectRaw('
                provider,
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(cost) as total_cost,
                AVG(cost) as avg_cost
            ', [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
            ->where('created_at', '>=', $timeWindow)
            ->groupBy('provider');

            if ($this->selectedChannel !== 'all') {
                $query->where('channel', $this->selectedChannel);
            }

            return $query->get()->map(function ($stat) {
                $stat->delivery_rate = $stat->total > 0 ? round(($stat->delivered / $stat->total) * 100, 2) : 0;
                $stat->failure_rate = $stat->total > 0 ? round(($stat->failed / $stat->total) * 100, 2) : 0;
                return $stat;
            });
        });
    }

    public function getChannelStatsProperty()
    {
        $cacheKey = "messenger_channel_stats_{$this->timeRange}_{$this->selectedProvider}";

        return Cache::remember($cacheKey, 300, function () {
            $timeWindow = $this->getTimeWindow();

            $query = Message::selectRaw('
                channel,
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(cost) as total_cost
            ', [MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
            ->where('created_at', '>=', $timeWindow)
            ->groupBy('channel');

            if ($this->selectedProvider !== 'all') {
                $query->where('provider', $this->selectedProvider);
            }

            return $query->get()->map(function ($stat) {
                $stat->delivery_rate = $stat->total > 0 ? round(($stat->delivered / $stat->total) * 100, 2) : 0;
                return $stat;
            });
        });
    }

    public function getRecentActivityProperty()
    {
        $cacheKey = "messenger_recent_activity_{$this->selectedProvider}_{$this->selectedChannel}";

        return Cache::remember($cacheKey, 60, function () {
            $query = Message::with(['batch'])
                ->select(['id', 'to', 'provider', 'channel', 'status', 'cost', 'created_at', 'batch_id'])
                ->orderBy('created_at', 'desc')
                ->limit(20);

            if ($this->selectedProvider !== 'all') {
                $query->where('provider', $this->selectedProvider);
            }

            if ($this->selectedChannel !== 'all') {
                $query->where('channel', $this->selectedChannel);
            }

            return $query->get()->map(function ($message) {
                return [
                    'id' => $message->id,
                    'recipient' => substr($message->to, 0, 3) . '****' . substr($message->to, -3),
                    'provider' => $message->provider,
                    'channel' => $message->channel,
                    'status' => is_object($message->status) ? $message->status->value : $message->status,
                    'cost' => $message->cost,
                    'time' => $message->created_at->diffForHumans(),
                    'batch_name' => $message->batch?->name,
                ];
            });
        });
    }

    public function getSystemHealthProperty()
    {
        return app(MonitoringService::class)->getSystemHealth();
    }

    public function getRealtimeStatsProperty()
    {
        return app(MonitoringService::class)->getRealtimeStats();
    }

    private function getTimeWindow()
    {
        return match($this->timeRange) {
            '1h' => Carbon::now()->subHour(),
            '6h' => Carbon::now()->subHours(6),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            '90d' => Carbon::now()->subDays(90),
            default => Carbon::now()->subDay(),
        };
    }

    private function calculateAvgDeliveryTime($query)
    {
        $avgSeconds = $query->whereNotNull('delivered_at')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, delivered_at)'));

        return $avgSeconds ? round($avgSeconds, 2) : null;
    }

    public function getTimeRangeOptionsProperty()
    {
        return [
            '1h' => 'Last Hour',
            '6h' => 'Last 6 Hours',
            '24h' => 'Last 24 Hours',
            '7d' => 'Last 7 Days',
            '30d' => 'Last 30 Days',
            '90d' => 'Last 90 Days',
        ];
    }

    public function getProviderOptionsProperty()
    {
        $providers = ['all' => 'All Providers'];

        $activeProviders = Message::distinct('provider')
            ->whereNotNull('provider')
            ->pluck('provider')
            ->toArray();

        foreach ($activeProviders as $provider) {
            $providers[$provider] = ucfirst(str_replace('_', ' ', $provider));
        }

        return $providers;
    }

    public function getChannelOptionsProperty()
    {
        $channels = ['all' => 'All Channels'];

        $activeChannels = Message::distinct('channel')
            ->whereNotNull('channel')
            ->pluck('channel')
            ->toArray();

        foreach ($activeChannels as $channel) {
            $channels[$channel] = strtoupper($channel);
        }

        return $channels;
    }

    public function render()
    {
        return view('livewire.messenger-analytics-dashboard', [
            'metrics' => $this->metrics,
            'hourlyData' => $this->hourlyData,
            'providerStats' => $this->providerStats,
            'channelStats' => $this->channelStats,
            'recentActivity' => $this->recentActivity,
            'systemHealth' => $this->systemHealth,
            'realtimeStats' => $this->realtimeStats,
            'timeRangeOptions' => $this->timeRangeOptions,
            'providerOptions' => $this->providerOptions,
            'channelOptions' => $this->channelOptions,
        ]);
    }
}
