<?php

namespace Tests\Feature\Messenger\Models;

use Tests\TestCase;
use App\Messenger\Models\Consent;
use App\Messenger\Enums\ConsentStatus;
use App\Messenger\Enums\ConsentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_consent_record()
    {
        $consent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $this->assertInstanceOf(Consent::class, $consent);
        $this->assertEquals('+1234567890', $consent->recipient_phone);
        $this->assertEquals(ConsentType::MARKETING, $consent->type);
        $this->assertEquals(ConsentStatus::GRANTED, $consent->status);
    }

    /** @test */
    public function it_has_granted_scope()
    {
        // Create granted consent
        Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        // Create revoked consent
        Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::REVOKED,
            'granted_at' => now()->subWeek(),
            'revoked_at' => now(),
        ]);

        $grantedConsents = Consent::granted()->get();

        $this->assertCount(1, $grantedConsents);
        $this->assertEquals('+1234567890', $grantedConsents->first()->recipient_phone);
    }

    /** @test */
    public function it_has_revoked_scope()
    {
        // Create granted consent
        Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        // Create revoked consent
        Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::REVOKED,
            'granted_at' => now()->subWeek(),
            'revoked_at' => now(),
        ]);

        $revokedConsents = Consent::revoked()->get();

        $this->assertCount(1, $revokedConsents);
        $this->assertEquals('+0987654321', $revokedConsents->first()->recipient_phone);
    }

    /** @test */
    public function it_has_pending_scope()
    {
        // Create pending consent
        Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::PENDING,
            'verification_token' => 'test-token',
        ]);

        // Create granted consent
        Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $pendingConsents = Consent::pending()->get();

        $this->assertCount(1, $pendingConsents);
        $this->assertEquals('+1234567890', $pendingConsents->first()->recipient_phone);
    }

    /** @test */
    public function it_has_for_phone_scope()
    {
        $phone = '+1234567890';

        Consent::create([
            'recipient_phone' => $phone,
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $phoneConsents = Consent::forPhone($phone)->get();

        $this->assertCount(1, $phoneConsents);
        $this->assertEquals($phone, $phoneConsents->first()->recipient_phone);
    }

    /** @test */
    public function it_has_of_type_scope()
    {
        Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::NOTIFICATIONS,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $marketingConsents = Consent::ofType(ConsentType::MARKETING)->get();

        $this->assertCount(1, $marketingConsents);
        $this->assertEquals(ConsentType::MARKETING, $marketingConsents->first()->type);
    }

    /** @test */
    public function it_has_expired_scope()
    {
        // Create expired consent
        Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => Carbon::now()->subDays(config('messenger.consent.retention_days', 2555) + 1),
        ]);

        // Create valid consent
        Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $expiredConsents = Consent::expired()->get();

        $this->assertCount(1, $expiredConsents);
        $this->assertEquals('+1234567890', $expiredConsents->first()->recipient_phone);
    }

    /** @test */
    public function it_can_check_if_consent_is_active()
    {
        $activeConsent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $revokedConsent = Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::REVOKED,
            'granted_at' => now()->subWeek(),
            'revoked_at' => now(),
        ]);

        $this->assertTrue($activeConsent->isActive());
        $this->assertFalse($revokedConsent->isActive());
    }

    /** @test */
    public function it_can_check_if_consent_is_expired()
    {
        $validConsent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $expiredConsent = Consent::create([
            'recipient_phone' => '+0987654321',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => Carbon::now()->subDays(config('messenger.consent.retention_days', 2555) + 1),
        ]);

        $this->assertFalse($validConsent->isExpired());
        $this->assertTrue($expiredConsent->isExpired());
    }

    /** @test */
    public function it_can_revoke_consent()
    {
        $consent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $result = $consent->revoke();

        $this->assertTrue($result);
        $this->assertEquals(ConsentStatus::REVOKED, $consent->status);
        $this->assertNotNull($consent->revoked_at);
    }

    /** @test */
    public function it_can_grant_consent()
    {
        $consent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::PENDING,
            'verification_token' => 'test-token',
        ]);

        $result = $consent->grant();

        $this->assertTrue($result);
        $this->assertEquals(ConsentStatus::GRANTED, $consent->status);
        $this->assertNotNull($consent->granted_at);
        $this->assertNull($consent->verification_token);
    }

    /** @test */
    public function it_can_update_preferences()
    {
        $consent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $preferences = [
            'frequency' => 'weekly',
            'topics' => ['offers', 'updates'],
        ];

        $result = $consent->updatePreferences($preferences);

        $this->assertTrue($result);
        $this->assertEquals($preferences, $consent->preferences);
    }

    /** @test */
    public function it_can_anonymize_data()
    {
        $consent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $originalPhone = $consent->recipient_phone;
        $result = $consent->anonymize();

        $this->assertTrue($result);
        $this->assertNotEquals($originalPhone, $consent->recipient_phone);
        $this->assertStringStartsWith('ANON_', $consent->recipient_phone);
        $this->assertNotNull($consent->anonymized_at);
    }

    /** @test */
    public function it_casts_preferences_to_array()
    {
        $preferences = [
            'frequency' => 'weekly',
            'topics' => ['offers', 'updates'],
        ];

        $consent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
            'preferences' => $preferences,
        ]);

        $this->assertIsArray($consent->preferences);
        $this->assertEquals($preferences, $consent->preferences);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $consent = new Consent();

        $expectedFillable = [
            'recipient_phone',
            'type',
            'status',
            'verification_token',
            'granted_at',
            'revoked_at',
            'anonymized_at',
            'preferences',
        ];

        $this->assertEquals($expectedFillable, $consent->getFillable());
    }

    /** @test */
    public function it_has_correct_date_casts()
    {
        $consent = Consent::create([
            'recipient_phone' => '+1234567890',
            'type' => ConsentType::MARKETING,
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
        ]);

        $this->assertInstanceOf(Carbon::class, $consent->granted_at);
        $this->assertInstanceOf(Carbon::class, $consent->created_at);
        $this->assertInstanceOf(Carbon::class, $consent->updated_at);
    }
}
