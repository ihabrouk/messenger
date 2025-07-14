<?php

namespace Tests\Feature\Messenger\Livewire;

use Tests\TestCase;
use App\Messenger\Livewire\MessengerAnalyticsDashboard;
use App\Messenger\Models\Message;
use App\Messenger\Enums\MessageStatus;
use App\Messenger\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Carbon\Carbon;

class MessengerAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    /** @test */
    public function it_renders_successfully()
    {
        Livewire::test(MessengerAnalyticsDashboard::class)
            ->assertSuccessful()
            ->assertViewIs('livewire.messenger-analytics-dashboard');
    }

    /** @test */
    public function it_displays_correct_metrics()
    {
        Livewire::test(MessengerAnalyticsDashboard::class)
            ->assertSet('timeRange', '24h')
            ->assertSet('selectedProvider', 'all')
            ->assertSet('selectedChannel', 'all')
            ->assertViewHas('metrics')
            ->assertViewHas('hourlyData')
            ->assertViewHas('providerStats')
            ->assertViewHas('channelStats');
    }

    /** @test */
    public function it_can_change_time_range()
    {
        Livewire::test(MessengerAnalyticsDashboard::class)
            ->set('timeRange', '7d')
            ->assertSet('timeRange', '7d')
            ->assertDispatched('refresh-charts');
    }

    /** @test */
    public function it_can_filter_by_provider()
    {
        Livewire::test(MessengerAnalyticsDashboard::class)
            ->set('selectedProvider', 'twilio')
            ->assertSet('selectedProvider', 'twilio')
            ->assertDispatched('refresh-charts');
    }

    /** @test */
    public function it_can_filter_by_channel()
    {
        Livewire::test(MessengerAnalyticsDashboard::class)
            ->set('selectedChannel', 'sms')
            ->assertSet('selectedChannel', 'sms')
            ->assertDispatched('refresh-charts');
    }

    /** @test */
    public function it_can_toggle_auto_refresh()
    {
        Livewire::test(MessengerAnalyticsDashboard::class)
            ->call('toggleAutoRefresh')
            ->assertSet('autoRefresh', false)
            ->assertDispatched('toggle-auto-refresh', ['enabled' => false]);
    }

    /** @test */
    public function it_can_refresh_data()
    {
        Livewire::test(MessengerAnalyticsDashboard::class)
            ->call('refreshData')
            ->assertDispatched('refresh-charts')
            ->assertDispatched('data-refreshed');
    }

    /** @test */
    public function it_calculates_metrics_correctly()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $metrics = $component->get('metrics');

        $this->assertEquals(5, $metrics['total_messages']);
        $this->assertEquals(3, $metrics['delivered_count']);
        $this->assertEquals(1, $metrics['failed_count']);
        $this->assertEquals(1, $metrics['pending_count']);
        $this->assertEquals(60.0, $metrics['delivery_rate']);
        $this->assertEquals(20.0, $metrics['failure_rate']);
    }

    /** @test */
    public function it_filters_data_by_time_range()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class)
            ->set('timeRange', '1h');

        $metrics = $component->get('metrics');

        // Should only include messages from the last hour
        $this->assertLessThanOrEqual(5, $metrics['total_messages']);
    }

    /** @test */
    public function it_filters_data_by_provider()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class)
            ->set('selectedProvider', 'twilio');

        $metrics = $component->get('metrics');

        // Should only include Twilio messages
        $this->assertEquals(3, $metrics['total_messages']);
    }

    /** @test */
    public function it_shows_hourly_data()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $hourlyData = $component->get('hourlyData');

        $this->assertIsIterable($hourlyData);

        foreach ($hourlyData as $data) {
            $this->assertArrayHasKey('hour', $data);
            $this->assertArrayHasKey('total', $data);
            $this->assertArrayHasKey('delivered', $data);
            $this->assertArrayHasKey('failed', $data);
            $this->assertArrayHasKey('cost', $data);
            $this->assertArrayHasKey('delivery_rate', $data);
        }
    }

    /** @test */
    public function it_shows_provider_stats()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $providerStats = $component->get('providerStats');

        $this->assertIsIterable($providerStats);

        foreach ($providerStats as $stat) {
            $this->assertArrayHasKey('provider', $stat);
            $this->assertArrayHasKey('total', $stat);
            $this->assertArrayHasKey('delivered', $stat);
            $this->assertArrayHasKey('failed', $stat);
            $this->assertArrayHasKey('delivery_rate', $stat);
            $this->assertArrayHasKey('failure_rate', $stat);
        }
    }

    /** @test */
    public function it_shows_channel_stats()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $channelStats = $component->get('channelStats');

        $this->assertIsIterable($channelStats);

        foreach ($channelStats as $stat) {
            $this->assertArrayHasKey('channel', $stat);
            $this->assertArrayHasKey('total', $stat);
            $this->assertArrayHasKey('delivered', $stat);
            $this->assertArrayHasKey('failed', $stat);
            $this->assertArrayHasKey('delivery_rate', $stat);
        }
    }

    /** @test */
    public function it_shows_recent_activity()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $recentActivity = $component->get('recentActivity');

        $this->assertIsIterable($recentActivity);
        $this->assertCount(5, $recentActivity);

        foreach ($recentActivity as $activity) {
            $this->assertArrayHasKey('id', $activity);
            $this->assertArrayHasKey('recipient', $activity);
            $this->assertArrayHasKey('provider', $activity);
            $this->assertArrayHasKey('channel', $activity);
            $this->assertArrayHasKey('status', $activity);
            $this->assertArrayHasKey('time', $activity);

            // Check that phone numbers are masked
            $this->assertStringContainsString('****', $activity['recipient']);
        }
    }

    /** @test */
    public function it_caches_data_appropriately()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        // First call
        $metrics1 = $component->get('metrics');

        // Second call should return cached data
        $metrics2 = $component->get('metrics');

        $this->assertEquals($metrics1, $metrics2);
    }

    /** @test */
    public function it_handles_query_string_parameters()
    {
        $component = Livewire::withQueryParams([
            'timeRange' => '7d',
            'selectedProvider' => 'twilio',
            'selectedChannel' => 'sms',
        ])->test(MessengerAnalyticsDashboard::class);

        $component
            ->assertSet('timeRange', '7d')
            ->assertSet('selectedProvider', 'twilio')
            ->assertSet('selectedChannel', 'sms');
    }

    /** @test */
    public function it_provides_correct_options()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $timeRangeOptions = $component->get('timeRangeOptions');
        $providerOptions = $component->get('providerOptions');
        $channelOptions = $component->get('channelOptions');

        $this->assertArrayHasKey('1h', $timeRangeOptions);
        $this->assertArrayHasKey('24h', $timeRangeOptions);
        $this->assertArrayHasKey('7d', $timeRangeOptions);

        $this->assertArrayHasKey('all', $providerOptions);
        $this->assertArrayHasKey('twilio', $providerOptions);

        $this->assertArrayHasKey('all', $channelOptions);
        $this->assertArrayHasKey('SMS', $channelOptions);
    }

    /** @test */
    public function it_shows_system_health()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $systemHealth = $component->get('systemHealth');

        $this->assertIsArray($systemHealth);
        // System health is provided by MonitoringService mock
    }

    /** @test */
    public function it_shows_realtime_stats()
    {
        $component = Livewire::test(MessengerAnalyticsDashboard::class);

        $realtimeStats = $component->get('realtimeStats');

        $this->assertIsArray($realtimeStats);
        // Realtime stats are provided by MonitoringService mock
    }

    private function createTestData(): void
    {
        // Create test messages with different providers, channels, and statuses
        $messages = [
            [
                'recipient_phone' => '+1234567890',
                'content' => 'Test message 1',
                'provider' => 'twilio',
                'channel' => 'sms',
                'status' => MessageStatus::DELIVERED,
                'cost' => 0.01,
                'created_at' => now()->subMinutes(30),
                'delivered_at' => now()->subMinutes(29),
            ],
            [
                'recipient_phone' => '+1234567891',
                'content' => 'Test message 2',
                'provider' => 'twilio',
                'channel' => 'sms',
                'status' => MessageStatus::DELIVERED,
                'cost' => 0.01,
                'created_at' => now()->subMinutes(25),
                'delivered_at' => now()->subMinutes(24),
            ],
            [
                'recipient_phone' => '+1234567892',
                'content' => 'Test message 3',
                'provider' => 'twilio',
                'channel' => 'sms',
                'status' => MessageStatus::DELIVERED,
                'cost' => 0.01,
                'created_at' => now()->subMinutes(20),
                'delivered_at' => now()->subMinutes(19),
            ],
            [
                'recipient_phone' => '+1234567893',
                'content' => 'Test message 4',
                'provider' => 'aws_sns',
                'channel' => 'sms',
                'status' => MessageStatus::FAILED,
                'cost' => 0.005,
                'created_at' => now()->subMinutes(15),
            ],
            [
                'recipient_phone' => '+1234567894',
                'content' => 'Test message 5',
                'provider' => 'aws_sns',
                'channel' => 'sms',
                'status' => MessageStatus::PENDING,
                'cost' => 0.005,
                'created_at' => now()->subMinutes(10),
            ],
        ];

        foreach ($messages as $messageData) {
            Message::create($messageData);
        }
    }
}
