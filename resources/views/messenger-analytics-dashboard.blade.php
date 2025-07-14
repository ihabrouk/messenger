<div class="space-y-6" wire:poll.30s="refreshData">
    {{-- Dashboard Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Messenger Analytics
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Real-time messaging analytics and system health
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Auto Refresh Toggle --}}
            <button 
                wire:click="toggleAutoRefresh"
                class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium {{ $autoRefresh ? 'bg-green-50 text-green-700 border-green-300' : 'bg-white text-gray-700' }} hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ $autoRefresh ? 'Auto Refresh ON' : 'Auto Refresh OFF' }}
            </button>

            {{-- Manual Refresh --}}
            <button 
                wire:click="refreshData"
                class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Time Range
                </label>
                <select wire:model.live="timeRange" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($timeRangeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Provider
                </label>
                <select wire:model.live="selectedProvider" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($providerOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Channel
                </label>
                <select wire:model.live="selectedChannel" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($channelOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- System Health Alert --}}
    @if($systemHealth['queue_status']['health'] !== 'healthy' || collect($systemHealth['provider_health'])->contains('status', '!=', 'healthy'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                        System Health Issues Detected
                    </h3>
                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                        <ul class="list-disc pl-5 space-y-1">
                            @if($systemHealth['queue_status']['health'] !== 'healthy')
                                <li>Queue health: {{ ucfirst($systemHealth['queue_status']['health']) }}</li>
                            @endif
                            @foreach($systemHealth['provider_health'] as $provider => $health)
                                @if($health['status'] !== 'healthy')
                                    <li>{{ ucfirst(str_replace('_', ' ', $provider)) }}: {{ ucfirst($health['status']) }} ({{ $health['success_rate'] }}% success rate)</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Real-time Stats --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Real-time System Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold {{ $realtimeStats['system_status'] === 'healthy' ? 'text-green-600' : ($realtimeStats['system_status'] === 'warning' ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $realtimeStats['messages_last_minute'] }}
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Messages/minute</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $realtimeStats['active_queues'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Active in Queue</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-600">{{ $realtimeStats['current_throughput'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Delivered/minute</div>
            </div>
            <div class="text-center">
                <div class="flex items-center justify-center">
                    <span class="w-3 h-3 rounded-full mr-2 {{ $realtimeStats['system_status'] === 'healthy' ? 'bg-green-500' : ($realtimeStats['system_status'] === 'warning' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ ucfirst($realtimeStats['system_status']) }}
                    </span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">System Status</div>
            </div>
        </div>
    </div>

    {{-- Key Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Messages</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['total_messages']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Delivery Rate</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['delivery_rate'], 1) }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Failed Messages</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['failed_count']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                        </svg>
                    </div>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Cost</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">${{ number_format($metrics['total_cost'], 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Hourly Trend Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Message Volume Trend</h3>
            <div class="h-64" id="hourly-chart">
                {{-- Chart will be rendered here with JavaScript --}}
                <div class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p class="mt-2 text-sm">Chart data loading...</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Provider Performance --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Provider Performance</h3>
            <div class="space-y-4">
                @forelse($providerStats as $provider)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-3 {{ $provider->delivery_rate >= 95 ? 'bg-green-500' : ($provider->delivery_rate >= 90 ? 'bg-yellow-500' : 'bg-red-500') }}"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ ucfirst(str_replace('_', ' ', $provider->provider)) }}
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($provider->delivery_rate, 1) }}%</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($provider->total) }} messages</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No provider data available for the selected time range.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Activity --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Recipient</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Provider</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cost</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($recentActivity as $activity)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $activity['recipient'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $activity['provider'])) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ strtoupper($activity['channel']) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $activity['status'] === 'delivered' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : '' }}
                                    {{ $activity['status'] === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400' : '' }}
                                    {{ in_array($activity['status'], ['pending', 'queued', 'sending']) ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400' : '' }}
                                ">
                                    {{ ucfirst($activity['status']) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${{ number_format($activity['cost'], 4) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $activity['time'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No recent activity found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- JavaScript for Charts and Auto-refresh --}}
    @push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            let autoRefreshInterval;

            // Handle auto-refresh toggle
            Livewire.on('toggle-auto-refresh', (event) => {
                if (event[0].enabled) {
                    autoRefreshInterval = setInterval(() => {
                        @this.call('refreshData');
                    }, 30000); // 30 seconds
                } else {
                    clearInterval(autoRefreshInterval);
                }
            });

            // Handle data refresh notifications
            Livewire.on('data-refreshed', () => {
                // Show a subtle notification
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-100 border border-green-300 text-green-700 px-4 py-2 rounded-md text-sm z-50';
                notification.textContent = 'Data refreshed';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 2000);
            });

            // Initialize auto-refresh if enabled
            if (@js($autoRefresh)) {
                autoRefreshInterval = setInterval(() => {
                    @this.call('refreshData');
                }, 30000);
            }
        });
    </script>
    @endpush
</div>
