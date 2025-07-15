<?php

use Ihabrouk\Messenger\Services\MessengerService;
use Ihabrouk\Messenger\Tests\TestCase;

class MessengerServiceTest extends TestCase
{
    public function test_messenger_service_can_be_resolved(): void
    {
        $service = $this->app->make(MessengerService::class);
        
        $this->assertInstanceOf(MessengerService::class, $service);
    }

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('messenger'));
        $this->assertNotNull(config('messenger.default'));
    }

    public function test_providers_are_configured(): void
    {
        $providers = config('messenger.providers');
        
        $this->assertIsArray($providers);
        $this->assertArrayHasKey('smsmisr', $providers);
        $this->assertArrayHasKey('twilio', $providers);
    }
}
