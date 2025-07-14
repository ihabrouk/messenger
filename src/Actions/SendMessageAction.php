<?php

namespace App\Messenger\Actions;

use App\Messenger\Services\MessengerService;
use App\Messenger\Models\Template;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Closure;

class SendMessageAction extends Action
{
    protected ?string $phoneField = 'phone_number';
    protected ?string $nameField = 'name';
    protected bool $requiresConfirmation = true;

    public static function getDefaultName(): ?string
    {
        return 'send_message';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Send Message')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('primary')
            ->requiresConfirmation($this->requiresConfirmation)
            ->modalHeading('Send Message')
            ->modalDescription('Send an SMS or WhatsApp message to this contact')
            ->modalSubmitActionLabel('Send Message')
            ->modalWidth('2xl');

        $this->form([
            Forms\Components\Section::make('Recipient Information')
                ->schema([
                    Forms\Components\TextInput::make('recipient_phone')
                        ->label('Phone Number')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('recipient_name')
                        ->label('Recipient Name')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Message Configuration')
                ->schema([
                    Forms\Components\Select::make('provider')
                        ->label('Provider')
                        ->options([
                            'smsmisr' => 'SMS Misr (SMS)',
                            'twilio' => 'Twilio (SMS/WhatsApp)',
                            'mocktest' => 'Mock Test (Development)',
                        ])
                        ->default('smsmisr')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            // Update channel options based on provider
                            if ($state === 'smsmisr') {
                                $set('channel', 'sms');
                            }
                        }),

                    Forms\Components\Select::make('channel')
                        ->label('Channel')
                        ->options(function (Forms\Get $get) {
                            $provider = $get('provider');
                            return match($provider) {
                                'smsmisr' => ['sms' => 'SMS'],
                                'twilio' => [
                                    'sms' => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ],
                                default => ['sms' => 'SMS'],
                            };
                        })
                        ->default('sms')
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('message_type')
                        ->label('Message Type')
                        ->options([
                            'transactional' => 'Transactional',
                            'marketing' => 'Marketing',
                            'emergency' => 'Emergency',
                        ])
                        ->default('transactional')
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Message Content')
                ->schema([
                    Forms\Components\Toggle::make('use_template')
                        ->label('Use Template')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if (!$state) {
                                $set('template_id', null);
                                $set('variables', []);
                            }
                        }),

                    Forms\Components\Select::make('template_id')
                        ->label('Template')
                        ->options(function (Forms\Get $get) {
                            $channel = $get('channel') ?? 'sms';
                            return Template::where('is_active', true)
                                ->where('approval_status', 'approved')
                                ->whereJsonContains('channels', $channel)
                                ->get()
                                ->pluck('display_name', 'id');
                        })
                        ->searchable()
                        ->live()
                        ->visible(fn (Forms\Get $get) => $get('use_template'))
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state) {
                                $template = Template::find($state);
                                if ($template) {
                                    // Set up variables for the template
                                    $variables = [];
                                    foreach ($template->variables ?? [] as $var) {
                                        $variables[$var] = '';
                                    }
                                    $set('variables', $variables);
                                    $set('template_preview', $template->body);
                                }
                            }
                        }),

                    Forms\Components\KeyValue::make('variables')
                        ->label('Template Variables')
                        ->visible(fn (Forms\Get $get) => $get('use_template') && $get('template_id'))
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            // Update preview with variables
                            $templateId = $get('template_id');
                            if ($templateId && $state) {
                                $template = Template::find($templateId);
                                if ($template) {
                                    $preview = $template->body;
                                    foreach ($state as $key => $value) {
                                        $preview = str_replace("{{ {$key} }}", $value, $preview);
                                    }
                                    $set('template_preview', $preview);
                                }
                            }
                        }),

                    Forms\Components\Textarea::make('custom_message')
                        ->label('Custom Message')
                        ->rows(4)
                        ->maxLength(1600)
                        ->live(debounce: 500)
                        ->visible(fn (Forms\Get $get) => !$get('use_template'))
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $set('character_count', mb_strlen($state ?? ''));
                        }),

                    Forms\Components\Placeholder::make('template_preview')
                        ->label('Message Preview')
                        ->content(fn (Forms\Get $get) => $get('template_preview') ?? 'Template preview will appear here...')
                        ->visible(fn (Forms\Get $get) => $get('use_template')),

                    Forms\Components\TextInput::make('character_count')
                        ->label('Character Count')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (Forms\Get $get) => !$get('use_template'))
                        ->helperText('SMS: 160 chars per part, WhatsApp: 4096 chars max'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Cost Estimation')
                ->schema([
                    Forms\Components\Placeholder::make('estimated_cost')
                        ->label('Estimated Cost')
                        ->content(function (Forms\Get $get) {
                            $provider = $get('provider');
                            $channel = $get('channel');
                            $message = $get('use_template') ? $get('template_preview') : $get('custom_message');

                            if (!$message) return 'No message to estimate';

                            $length = mb_strlen($message);
                            $cost = 0.0;

                            // Simple cost calculation (should be moved to proper service)
                            if ($channel === 'sms') {
                                $parts = ceil($length / 160);
                                $cost = match($provider) {
                                    'smsmisr' => $parts * 0.05, // 5 cents per SMS part
                                    'twilio' => $parts * 0.075, // 7.5 cents per SMS part
                                    default => $parts * 0.01,
                                };
                            } else {
                                $cost = match($provider) {
                                    'twilio' => 0.05, // WhatsApp flat rate
                                    default => 0.01,
                                };
                            }

                            return '$' . number_format($cost, 3);
                        }),

                    Forms\Components\Placeholder::make('message_segments')
                        ->label('Message Segments')
                        ->content(function (Forms\Get $get) {
                            $channel = $get('channel');
                            $message = $get('use_template') ? $get('template_preview') : $get('custom_message');

                            if (!$message) return '0';

                            if ($channel === 'sms') {
                                return (string) ceil(mb_strlen($message) / 160);
                            }

                            return '1';
                        }),
                ])
                ->columns(2)
                ->collapsible(),
        ]);

        $this->action(function (array $data, Model $record): void {
            $this->sendMessage($data, $record);
        });

        // Pre-fill form with recipient data when the modal opens
        $this->fillForm(function (Model $record): array {
            $recipientPhone = $this->getPhoneNumber($record);
            $recipientName = $this->getRecipientName($record);

            return [
                'recipient_phone' => $recipientPhone,
                'recipient_name' => $recipientName,
            ];
        });
    }

    protected function sendMessage(array $data, Model $record): void
    {
        try {
            $messengerService = app(MessengerService::class);
            $recipientPhone = $this->getPhoneNumber($record);

            if (!$recipientPhone) {
                Notification::make()
                    ->title('Error')
                    ->body('No phone number found for this contact')
                    ->danger()
                    ->send();
                return;
            }

            $messageData = [
                'recipient_phone' => $recipientPhone,
                'type' => $data['message_type'],
                'provider' => $data['provider'],
                'channel' => $data['channel'],
                'metadata' => [
                    'source' => 'filament_action',
                    'record_type' => get_class($record),
                    'record_id' => $record->getKey(),
                    'sent_by' => auth()->id(),
                ],
            ];

            if ($data['use_template']) {
                $messageData['template_id'] = $data['template_id'];
                $messageData['variables'] = $data['variables'] ?? [];
            } else {
                $messageData['message'] = $data['custom_message'];
            }

            $result = $messengerService->send($messageData);

            if ($result->isSuccessful()) {
                Notification::make()
                    ->title('Message Sent Successfully')
                    ->body("Message sent to {$recipientPhone}")
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view_logs')
                            ->label('View Logs')
                            ->url(route('filament.admin.resources.message-logs.index'))
                            ->openUrlInNewTab(),
                    ])
                    ->send();
            } else {
                Notification::make()
                    ->title('Message Failed')
                    ->body($result->getErrorMessage() ?? 'Unknown error occurred')
                    ->danger()
                    ->send();
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Sending Message')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getPhoneNumber(Model $record): ?string
    {
        return $record->{$this->phoneField};
    }

    protected function getRecipientName(Model $record): ?string
    {
        if (!$this->nameField) {
            return null;
        }

        return $record->{$this->nameField} ?? $record->name ?? null;
    }

    public function phoneField(string $field): static
    {
        $this->phoneField = $field;
        return $this;
    }

    public function nameField(string $field): static
    {
        $this->nameField = $field;
        return $this;
    }

    public function requiresConfirmation(Closure|bool $condition = true): static
    {
        if ($condition instanceof Closure) {
            $this->requiresConfirmation = $condition();
        } else {
            $this->requiresConfirmation = $condition;
        }
        return $this;
    }
}
