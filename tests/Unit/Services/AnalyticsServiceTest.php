<?php

namespace Tests\Unit\Messenger\Services;

use Tests\TestCase;
use App\Messenger\Services\AnalyticsService;
use App\Messenger\Models\Message;
use App\Messenger\Models\Batch;
use App\Messenger\Models\Template;
use App\Messenger\Models\Consent;
use App\Messenger\Enums\MessageStatus;
use App\Messenger\Enums\ConsentStatus;
use App\Messenger\Enums\ConsentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = app(AnalyticsService::class);
    }

    /** @test */
    public function it_can_get_overview_metrics()
    {
        // Create test data
        $this->createTestMessages();

        $overview = $this->analyticsService->getOverviewMetrics();

        $this->assertArrayHasKey('total_messages', $overview);
        $this->assertArrayHasKey('delivered_count', $overview);
        $this->assertArrayHasKey('failed_count', $overview);
        $this->assertArrayHasKey('pending_count', $overview);
        $this->assertArrayHasKey('delivery_rate', $overview);
        $this->assertArrayHasKey('total_cost', $overview);
        $this->assertArrayHasKey('active_providers', $overview);
        $this->assertArrayHasKey('active_channels', $overview);

        $this->assertEquals(5, $overview['total_messages']);
        $this->assertEquals(3, $overview['delivered_count']);
        $this->assertEquals(1, $overview['failed_count']);
        $this->assertEquals(60.0, $overview['delivery_rate']);
    }

    /** @test */
    public function it_can_get_trend_analysis()
    {
        $this->createTestMessages();

        $trends = $this->analyticsService->getTrendAnalysis(
            Carbon::now()->subDay(),
            Carbon::now(),
            'hourly'
        );

        $this->assertIsArray($trends);
        $this->assertNotEmpty($trends);

        foreach ($trends as $trend) {
            $this->assertArrayHasKey('period', $trend);
            $this->assertArrayHasKey('total', $trend);
            $this->assertArrayHasKey('delivered', $trend);
            $this->assertArrayHasKey('failed', $trend);
            $this->assertArrayHasKey('cost', $trend);
        }
    }

    /** @test */
    public function it_can_get_cost_analysis()
    {
        $this->createTestMessages();

        $costAnalysis = $this->analyticsService->getCostAnalysis('2024-07');

        $this->assertArrayHasKey('total_cost', $costAnalysis);
        $this->assertArrayHasKey('avg_cost_per_message', $costAnalysis);
        $this->assertArrayHasKey('cost_by_provider', $costAnalysis);
        $this->assertArrayHasKey('cost_by_channel', $costAnalysis);
        $this->assertArrayHasKey('daily_costs', $costAnalysis);

        $this->assertGreaterThan(0, $costAnalysis['total_cost']);
    }

    /** @test */
    public function it_can_get_engagement_metrics()
    {
        $this->createTestMessages();

        $engagement = $this->analyticsService->getEngagementMetrics();

        $this->assertArrayHasKey('total_recipients', $engagement);
        $this->assertArrayHasKey('unique_recipients', $engagement);
        $this->assertArrayHasKey('repeat_recipients', $engagement);
        $this->assertArrayHasKey('avg_messages_per_recipient', $engagement);
        $this->assertArrayHasKey('engagement_rate', $engagement);
    }

    /** @test */
    public function it_can_get_provider_analytics()
    {
        $this->createTestMessages();

        $providerAnalytics = $this->analyticsService->getProviderAnalytics('twilio');

        $this->assertArrayHasKey('total_messages', $providerAnalytics);
        $this->assertArrayHasKey('delivery_rate', $providerAnalytics);
        $this->assertArrayHasKey('avg_response_time', $providerAnalytics);
        $this->assertArrayHasKey('total_cost', $providerAnalytics);
        $this->assertArrayHasKey('error_rate', $providerAnalytics);
        $this->assertArrayHasKey('channel_breakdown', $providerAnalytics);
    }

    /** @test */
    public function it_can_get_template_analytics()
    {
        $template = Template::create([
            'name' => 'test-template',
            'content' => 'Hello {{name}}!',
            'variables' => ['name'],
        ]);

        Message::create([
            'recipient_phone' => '+1234567890',
            'content' => 'Hello John!',
            'provider' => 'twilio',
            'channel' => 'sms',
            'status' => MessageStatus::DELIVERED,
            'template_id' => $template->id,
            'cost' => 0.01,
            'delivered_at' => now(),
        ]);

        $templateAnalytics = $this->analyticsService->getTemplateAnalytics();

        $this->assertIsArray($templateAnalytics);
        $this->assertNotEmpty($templateAnalytics);

        $firstTemplate = $templateAnalytics[0];
        $this->assertArrayHasKey('template_name', $firstTemplate);
        $this->assertArrayHasKey('usage_count', $firstTemplate);
        $this->assertArrayHasKey('delivery_rate', $firstTemplate);
        $this->assertArrayHasKey('avg_cost', $firstTemplate);
    }

    /** @test */
    public function it_can_get_consent_analytics()
    {
        // Create consent data
        Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::REVOKED,
            'granted_at' => now()->subWeek(),
            'revoked_at' => now(),
        ]);

        $consentAnalytics = $this->analyticsService->getConsentAnalytics();

        $this->assertArrayHasKey('total_consents', $consentAnalytics);
        $this->assertArrayHasKey('granted_consents', $consentAnalytics);
        $this->assertArrayHasKey('revoked_consents', $consentAnalytics);
        $this->assertArrayHasKey('pending_consents', $consentAnalytics);
        $this->assertArrayHasKey('consent_rate', $consentAnalytics);
        $this->assertArrayHasKey('consent_by_type', $consentAnalytics);

        $this->assertEquals(2, $consentAnalytics['total_consents']);
        $this->assertEquals(1, $consentAnalytics['granted_consents']);
        $this->assertEquals(1, $consentAnalytics['revoked_consents']);
    }

    /** @test */
    public function it_caches_analytics_data()
    {
        $this->createTestMessages();

        // First call should hit database and cache result
        $overview1 = $this->analyticsService->getOverviewMetrics();

        // Second call should hit cache
        $overview2 = $this->analyticsService->getOverviewMetrics();

        $this->assertEquals($overview1, $overview2);

        // Verify cache key exists
        $this->assertTrue(Cache::has('messenger.analytics.overview.'));
    }

    /** @test */
    public function it_can_filter_by_date_range()
    {
        // Create messages on different dates
        Message::create([
            'recipient_phone' => '+1234567890',
            'content' => 'Test message 1',
            'provider' => 'twilio',
            'channel' => 'sms',
            'status' => MessageStatus::DELIVERED,
            'cost' => 0.01,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        Message::create([
            'recipient_phone' => '+0987654321',
            'content' => 'Test message 2',
            'provider' => 'twilio',
            'channel' => 'sms',
            'status' => MessageStatus::DELIVERED,
            'cost' => 0.01,
            'created_at' => Carbon::now(),
        ]);

        $overview = $this->analyticsService->getOverviewMetrics(
            Carbon::now()->subDay(),
            Carbon::now()
        );

        $this->assertEquals(1, $overview['total_messages']);
    }

    /** @test */
    public function it_can_filter_by_provider()
    {
        Message::create([
            'recipient_phone' => '+1234567890',
            'content' => 'Test message 1',
            'provider' => 'twilio',
            'channel' => 'sms',
            'status' => MessageStatus::DELIVERED,
            'cost' => 0.01,
        ]);

        Message::create([
            'recipient_phone' => '+0987654321',
            'content' => 'Test message 2',
            'provider' => 'aws_sns',
            'channel' => 'sms',
            'status' => MessageStatus::DELIVERED,
            'cost' => 0.005,
        ]);

        $overview = $this->analyticsService->getOverviewMetrics(
            provider: 'twilio'
        );

        $this->assertEquals(1, $overview['total_messages']);
        $this->assertEquals(0.01, $overview['total_cost']);
    }

    /** @test */
    public function it_handles_empty_data_gracefully()
    {
        $overview = $this->analyticsService->getOverviewMetrics();

        $this->assertEquals(0, $overview['total_messages']);
        $this->assertEquals(0, $overview['delivered_count']);
        $this->assertEquals(0, $overview['total_cost']);
        $this->assertEquals(0, $overview['delivery_rate']);
    }

    /** @test */
    public function it_calculates_performance_metrics()
    {
        $this->createTestMessages();

        $performance = $this->analyticsService->getPerformanceMetrics();

        $this->assertArrayHasKey('avg_delivery_time', $performance);
        $this->assertArrayHasKey('throughput', $performance);
        $this->assertArrayHasKey('error_rate', $performance);
        $this->assertArrayHasKey('cost_efficiency', $performance);
    }

    private function createTestMessages(): void
    {
        $statuses = [
            MessageStatus::DELIVERED,
            MessageStatus::DELIVERED,
            MessageStatus::DELIVERED,
            MessageStatus::FAILED,
            MessageStatus::PENDING,
        ];

        foreach ($statuses as $index => $status) {
            Message::create([
                'recipient_phone' => '+123456789' . $index,
                'content' => "Test message {$index}",
                'provider' => 'twilio',
                'channel' => 'sms',
                'status' => $status,
                'cost' => 0.01,
                'created_at' => now()->subMinutes($index * 10),
                'delivered_at' => $status === MessageStatus::DELIVERED ? now()->subMinutes($index * 10)->addSeconds(5) : null,
            ]);
        }
    }
}
