<?php

namespace Tests\Unit\Messenger\Models;

use Tests\TestCase;
use App\Messenger\Models\Consent;
use App\Messenger\Enums\ConsentStatus;
use App\Messenger\Enums\ConsentType;

class ConsentBasicTest extends TestCase
{
    /** @test */
    public function it_can_create_consent_enum_instances()
    {
        $marketingType = ConsentType::MARKETING;
        $grantedStatus = ConsentStatus::GRANTED;

        $this->assertEquals('marketing', $marketingType->value);
        $this->assertEquals('granted', $grantedStatus->value);
        $this->assertEquals('Marketing', $marketingType->label());
        $this->assertEquals('Granted', $grantedStatus->label());
    }

    /** @test */
    public function it_has_all_required_consent_types()
    {
        $expectedTypes = ['marketing', 'notifications', 'reminders', 'alerts', 'transactional', 'all'];
        $actualTypes = array_map(fn($case) => $case->value, ConsentType::cases());

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $actualTypes);
        }
    }

    /** @test */
    public function it_has_all_required_consent_statuses()
    {
        $expectedStatuses = ['pending', 'granted', 'revoked', 'expired'];
        $actualStatuses = array_map(fn($case) => $case->value, ConsentStatus::cases());

        foreach ($expectedStatuses as $status) {
            $this->assertContains($status, $actualStatuses);
        }
    }

    /** @test */
    public function consent_model_exists_and_has_correct_attributes()
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
    public function consent_model_has_correct_casts()
    {
        $consent = new Consent();
        $casts = $consent->getCasts();

        $this->assertArrayHasKey('type', $casts);
        $this->assertArrayHasKey('status', $casts);
        $this->assertArrayHasKey('preferences', $casts);
        $this->assertArrayHasKey('granted_at', $casts);
        $this->assertArrayHasKey('revoked_at', $casts);
        $this->assertArrayHasKey('anonymized_at', $casts);
    }
}
