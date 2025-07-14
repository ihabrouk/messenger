<?php

namespace Tests\Unit\Messenger\Services;

use Tests\TestCase;
use App\Messenger\Services\ConsentService;
use App\Messenger\Models\Consent;
use App\Messenger\Enums\ConsentStatus;
use App\Messenger\Enums\ConsentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ConsentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsentService $consentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consentService = app(ConsentService::class);
    }

    /** @test */
    public function it_can_process_opt_in()
    {
        $phone = '+1234567890';
        $type = ConsentType::MARKETING;

        $consent = $this->consentService->processOptIn($phone, $type->value);

        $this->assertInstanceOf(Consent::class, $consent);
        $this->assertEquals($phone, $consent->recipient_phone);
        $this->assertEquals($type, $consent->type);
        $this->assertEquals(ConsentStatus::PENDING, $consent->status);
        $this->assertNotNull($consent->verification_token);
    }

    /** @test */
    public function it_can_process_opt_out()
    {
        // Create existing consent
        $phone = '+1234567890';
        $consent = Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $result = $this->consentService->processOptOut($phone);

        $this->assertTrue($result);
        $consent->refresh();
        $this->assertEquals(ConsentStatus::REVOKED, $consent->status);
        $this->assertNotNull($consent->revoked_at);
    }

    /** @test */
    public function it_can_verify_consent()
    {
        $phone = '+1234567890';
        $token = 'test-token-123';

        $consent = Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::PENDING,
            'verification_token' => $token,
        ]);

        $result = $this->consentService->verifyConsent($token);

        $this->assertTrue($result);
        $consent->refresh();
        $this->assertEquals(ConsentStatus::GRANTED, $consent->status);
        $this->assertNotNull($consent->granted_at);
        $this->assertNull($consent->verification_token);
    }

    /** @test */
    public function it_can_check_consent_exists()
    {
        $phone = '+1234567890';

        // No consent exists
        $this->assertFalse($this->consentService->hasConsent($phone));

        // Create granted consent
        Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $this->assertTrue($this->consentService->hasConsent($phone));
        $this->assertTrue($this->consentService->hasConsent($phone, ConsentType::MARKETING->value));
        $this->assertFalse($this->consentService->hasConsent($phone, ConsentType::NOTIFICATIONS->value));
    }

    /** @test */
    public function it_can_process_sms_replies()
    {
        $phone = '+1234567890';

        // Test opt-in reply
        $result = $this->consentService->processSmsReply($phone, 'YES');
        $this->assertTrue($result);

        $consent = Consent::where('recipient_phone', $phone)->first();
        $this->assertEquals(ConsentStatus::GRANTED, $consent->status);

        // Test opt-out reply
        $result = $this->consentService->processSmsReply($phone, 'STOP');
        $this->assertTrue($result);

        $consent->refresh();
        $this->assertEquals(ConsentStatus::REVOKED, $consent->status);
    }

    /** @test */
    public function it_can_handle_bulk_operations()
    {
        $phones = ['+1234567890', '+0987654321', '+5555555555'];
        $type = ConsentType::NOTIFICATIONS;

        $results = $this->consentService->bulkOptIn($phones, $type->value);

        $this->assertCount(3, $results);
        $this->assertEquals(3, Consent::where('type', $type)->count());

        // Test bulk opt-out
        $optOutResults = $this->consentService->bulkOptOut($phones);
        $this->assertCount(3, $optOutResults);
        $this->assertEquals(3, Consent::where('status', ConsentStatus::REVOKED)->count());
    }

    /** @test */
    public function it_can_export_user_data()
    {
        $phone = '+1234567890';

        // Create consent records
        Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
            'preferences' => ['frequency' => 'weekly'],
        ]);

        $data = $this->consentService->exportUserData($phone);

        $this->assertArrayHasKey('consents', $data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertCount(1, $data['consents']);
    }

    /** @test */
    public function it_can_delete_user_data()
    {
        $phone = '+1234567890';

        // Create consent record
        Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $result = $this->consentService->deleteUserData($phone);

        $this->assertTrue($result);
        $this->assertEquals(0, Consent::where('recipient_phone', $phone)->count());
    }

    /** @test */
    public function it_can_anonymize_user_data()
    {
        $phone = '+1234567890';

        Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $result = $this->consentService->anonymizeUserData($phone);

        $this->assertTrue($result);
        
        $consent = Consent::where('recipient_phone', 'like', 'ANON_%')->first();
        $this->assertNotNull($consent);
        $this->assertStringStartsWith('ANON_', $consent->recipient_phone);
    }

    /** @test */
    public function it_caches_consent_checks()
    {
        $phone = '+1234567890';

        Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        // First call should hit database
        $result1 = $this->consentService->hasConsent($phone);
        $this->assertTrue($result1);

        // Second call should hit cache
        $result2 = $this->consentService->hasConsent($phone);
        $this->assertTrue($result2);

        // Verify cache key exists
        $cacheKey = "messenger.consent.{$phone}.marketing";
        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function it_handles_expired_consents()
    {
        $phone = '+1234567890';

        // Create expired consent
        Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => Carbon::now()->subDays(config('messenger.consent.retention_days', 2555) + 1),
        ]);

        $hasConsent = $this->consentService->hasConsent($phone);
        $this->assertFalse($hasConsent);
    }

    /** @test */
    public function it_can_update_preferences()
    {
        $phone = '+1234567890';

        $consent = Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $preferences = [
            'frequency' => 'weekly',
            'topics' => ['offers', 'updates'],
            'time_preference' => 'morning',
        ];

        $result = $this->consentService->updatePreferences($phone, $preferences);

        $this->assertTrue($result);
        $consent->refresh();
        $this->assertEquals($preferences, $consent->preferences);
    }

    /** @test */
    public function it_validates_phone_numbers()
    {
        $invalidPhones = ['', 'invalid', '123', '+123'];

        foreach ($invalidPhones as $phone) {
            $this->expectException(\InvalidArgumentException::class);
            $this->consentService->processOptIn($phone, ConsentType::MARKETING->value);
        }
    }

    /** @test */
    public function it_handles_duplicate_opt_ins()
    {
        $phone = '+1234567890';
        $type = ConsentType::MARKETING;

        // First opt-in
        $consent1 = $this->consentService->processOptIn($phone, $type->value);

        // Second opt-in should return existing consent
        $consent2 = $this->consentService->processOptIn($phone, $type->value);

        $this->assertEquals($consent1->id, $consent2->id);
        $this->assertEquals(1, Consent::where('recipient_phone', $phone)->count());
    }
}
