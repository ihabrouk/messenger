<?php

namespace App\Messenger\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;

class ChannelSelector extends Component
{
    protected string $view = 'messenger.components.channel-selector';

    protected ?string $provider = null;
    protected bool $showProviderInfo = true;

    public static function make(string $name = 'channel_selector'): static
    {
        return parent::make($name);
    }

    public function provider(?string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function showProviderInfo(bool $show = true): static
    {
        $this->showProviderInfo = $show;
        return $this;
    }

    public function getChildComponents(): array
    {
        return [
            Section::make('Provider & Channel Selection')
                ->schema([
                    Select::make('provider')
                        ->label('Message Provider')
                        ->options([
                            'smsmisr' => 'SMS Misr - Premium SMS/OTP Provider',
                            'twilio' => 'Twilio - SMS & WhatsApp Platform',
                            'mocktest' => 'Mock Test - Development Only',
                        ])
                        ->descriptions([
                            'smsmisr' => 'Best for SMS and OTP in MENA region',
                            'twilio' => 'Global reach with WhatsApp support',
                            'mocktest' => 'Testing environment, no real messages sent',
                        ])
                        ->default($this->provider ?? 'smsmisr')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            // Auto-select appropriate channel based on provider
                            if ($state === 'smsmisr') {
                                $set('channel', 'sms');
                                $set('provider_info', $this->getProviderInfo($state));
                            } elseif ($state === 'twilio') {
                                $set('provider_info', $this->getProviderInfo($state));
                            } else {
                                $set('channel', 'sms');
                                $set('provider_info', $this->getProviderInfo($state));
                            }
                        }),

                    Select::make('channel')
                        ->label('Message Channel')
                        ->options(function (Get $get) {
                            $provider = $get('provider');
                            return match($provider) {
                                'smsmisr' => [
                                    'sms' => 'SMS - Text Messages',
                                ],
                                'twilio' => [
                                    'sms' => 'SMS - Text Messages',
                                    'whatsapp' => 'WhatsApp - Rich Messages',
                                ],
                                'mocktest' => [
                                    'sms' => 'SMS - Test Messages',
                                ],
                                default => [
                                    'sms' => 'SMS - Text Messages',
                                ],
                            };
                        })
                        ->descriptions(function (Get $get) {
                            $provider = $get('provider');
                            return match($provider) {
                                'twilio' => [
                                    'sms' => 'Standard SMS with 160 character limit',
                                    'whatsapp' => 'Rich media support, longer messages',
                                ],
                                default => [
                                    'sms' => 'Standard SMS with 160 character limit',
                                ],
                            };
                        })
                        ->default('sms')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            $provider = $get('provider');
                            $set('channel_info', $this->getChannelInfo($provider, $state));
                        }),
                ])
                ->columns(2),

            Section::make('Provider Information')
                ->schema([
                    Placeholder::make('provider_info')
                        ->label('Provider Details')
                        ->content(fn (Get $get) => $get('provider_info') ?? 'Select a provider to see details'),

                    Placeholder::make('channel_info')
                        ->label('Channel Details')
                        ->content(fn (Get $get) => $get('channel_info') ?? 'Select a channel to see details'),

                    Placeholder::make('pricing_info')
                        ->label('Pricing Information')
                        ->content(function (Get $get) {
                            $provider = $get('provider');
                            $channel = $get('channel');
                            return $this->getPricingInfo($provider, $channel);
                        }),
                ])
                ->visible($this->showProviderInfo)
                ->columns(3)
                ->collapsible()
                ->collapsed(),
        ];
    }

    protected function getProviderInfo(string $provider): string
    {
        return match($provider) {
            'smsmisr' => '🇪🇬 SMS Misr provides premium SMS services in MENA region with high delivery rates and OTP specialization.',
            'twilio' => '🌍 Twilio offers global SMS and WhatsApp services with extensive API features and worldwide coverage.',
            'mocktest' => '🧪 Mock Test provider for development and testing. No real messages are sent.',
            default => 'Unknown provider',
        };
    }

    protected function getChannelInfo(string $provider, string $channel): string
    {
        return match([$provider, $channel]) {
            ['smsmisr', 'sms'] => '📱 SMS via SMS Misr: 160 chars per segment, Arabic/English/Unicode support',
            ['twilio', 'sms'] => '📱 SMS via Twilio: 160 chars per segment, global delivery, delivery receipts',
            ['twilio', 'whatsapp'] => '💬 WhatsApp via Twilio: Rich media, 4096 chars, templates required for marketing',
            ['mocktest', 'sms'] => '🧪 Test SMS: Simulated sending for development purposes',
            default => 'Channel information not available',
        };
    }

    protected function getPricingInfo(?string $provider, ?string $channel): string
    {
        if (!$provider || !$channel) {
            return 'Select provider and channel to see pricing';
        }

        return match([$provider, $channel]) {
            ['smsmisr', 'sms'] => '💰 ~$0.05 per SMS segment (160 chars)',
            ['twilio', 'sms'] => '💰 ~$0.075 per SMS segment (160 chars)',
            ['twilio', 'whatsapp'] => '💰 ~$0.05 per WhatsApp message (4096 chars)',
            ['mocktest', 'sms'] => '💰 Free - Development only',
            default => 'Pricing information not available',
        };
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function shouldShowProviderInfo(): bool
    {
        return $this->showProviderInfo;
    }
}
