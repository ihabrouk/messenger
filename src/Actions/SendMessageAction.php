<?php

namespace Ihabrouk\Messenger\Actions;

use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Exception;
use Ihabrouk\Messenger\Services\MessengerService;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Enums\TemplateCategory;
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
            Section::make('Recipient Information')
                ->schema([
                    TextInput::make('recipient_phone')
                        ->label('Phone Number')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('recipient_name')
                        ->label('Recipient Name')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Message Configuration')
                ->schema([
                    Select::make('provider')
                        ->label('Provider')
                        ->options([
                            'smsmisr' => 'SMS Misr (SMS)',
                            'twilio' => 'Twilio (SMS/WhatsApp)',
                            'mocktest' => 'Mock Test (Development)',
                        ])
                        ->default('smsmisr')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            // Update channel options based on provider
                            if ($state === 'smsmisr') {
                                $set('channel', 'sms');
                            }
                        }),

                    Select::make('channel')
                        ->label('Channel')
                        ->options(function (Get $get) {
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

                    Select::make('message_category')
                        ->label('Message Category')
                        ->options([
                            TemplateCategory::TRANSACTIONAL->value => TemplateCategory::TRANSACTIONAL->label(),
                            TemplateCategory::MARKETING->value => TemplateCategory::MARKETING->label(),
                            TemplateCategory::EMERGENCY->value => TemplateCategory::EMERGENCY->label(),
                        ])
                        ->default(TemplateCategory::TRANSACTIONAL->value)
                        ->required(),
                ])
                ->columns(3),

            Section::make('Message Content')
                ->schema([
                    Toggle::make('use_template')
                        ->label('Use Template')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) {
                                $set('template_id', null);
                                $set('variables', []);
                            }
                        }),

                    Select::make('template_id')
                        ->label('Template')
                        ->options(function (Get $get) {
                            $channel = $get('channel') ?? 'sms';
                            return Template::where('is_active', true)
                                ->where('approval_status', 'approved')
                                ->whereJsonContains('channels', $channel)
                                ->get()
                                ->pluck('display_name', 'id');
                        })
                        ->searchable()
                        ->live()
                        ->visible(fn (Get $get) => $get('use_template'))
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
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

                    KeyValue::make('variables')
                        ->label('Template Variables')
                        ->visible(fn (Get $get) => $get('use_template') && $get('template_id'))
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
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

                    Textarea::make('custom_message')
                        ->label('Custom Message')
                        ->rows(4)
                        ->maxLength(1600)
                        ->live(debounce: 500)
                        ->visible(fn (Get $get) => !$get('use_template'))
                        ->afterStateUpdated(function ($state, Set $set) {
                            $set('character_count', mb_strlen($state ?? ''));
                        }),

                    Placeholder::make('template_preview')
                        ->label('Message Preview')
                        ->content(fn (Get $get) => $get('template_preview') ?? 'Template preview will appear here...')
                        ->visible(fn (Get $get) => $get('use_template')),

                    TextInput::make('character_count')
                        ->label('Character Count')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (Get $get) => !$get('use_template'))
                        ->helperText('SMS: 160 chars per part, WhatsApp: 4096 chars max'),
                ])
                ->columns(1),

            Section::make('Cost Estimation')
                ->schema([
                    Placeholder::make('estimated_cost')
                        ->label('Estimated Cost')
                        ->content(function (Get $get) {
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

                    Placeholder::make('message_segments')
                        ->label('Message Segments')
                        ->content(function (Get $get) {
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

            // Determine MessageType based on channel
            $messageType = match($data['channel']) {
                'whatsapp' => 'whatsapp',
                'sms' => 'sms',
                default => 'sms',
            };

            $messageData = [
                'to' => $recipientPhone,
                'type' => $messageType,
                'provider' => $data['provider'],
                'metadata' => [
                    'source' => 'filament_action',
                    'record_type' => get_class($record),
                    'record_id' => $record->getKey(),
                    'sent_by' => auth()->id(),
                    'category' => $data['message_category'],
                    'channel' => $data['channel'],
                ],
            ];

            if ($data['use_template']) {
                $messageData['template_id'] = $data['template_id'];
                $messageData['variables'] = $data['variables'] ?? [];
                $messageData['message'] = ''; // Required property, will be populated from template
            } else {
                $messageData['message'] = $data['custom_message'];
            }

            // Convert array to SendMessageData object
            $sendMessageData = SendMessageData::fromArray($messageData);
            $result = $messengerService->send($sendMessageData);

            if ($result->isSuccessful()) {
                Notification::make()
                    ->title('Message Sent Successfully')
                    ->body("Message sent to {$recipientPhone}")
                    ->success()
                    ->actions([
                        Action::make('view_logs')
                            ->label('View Logs')
                            ->url(route('filament.admin.resources.message-logs.index'))
                            ->openUrlInNewTab(),
                    ])
                    ->send();
            } else {
                Notification::make()
                    ->title('Message Failed')
                    ->body($result->errorMessage ?? 'Unknown error occurred')
                    ->danger()
                    ->send();
            }

        } catch (Exception $e) {
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
