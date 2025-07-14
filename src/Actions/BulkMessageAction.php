<?php

namespace App\Messenger\Actions;

use App\Messenger\Services\MessengerService;
use App\Messenger\Services\BulkMessageService;
use App\Messenger\Models\Template;
use App\Messenger\Models\Batch;
use App\Messenger\Enums\MessageType;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Closure;

class BulkMessageAction extends BulkAction
{
    protected ?string $phoneField = 'phone_number';
    protected ?string $nameField = 'name';
    protected bool $requiresConfirmation = true;
    protected int $maxRecipients = 1000;

    public static function getDefaultName(): ?string
    {
        return 'bulk_send_message';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Send Bulk Message')
            ->icon('heroicon-o-megaphone')
            ->color('success')
            ->requiresConfirmation($this->requiresConfirmation)
            ->modalHeading('Send Bulk Message')
            ->modalDescription('Send messages to selected contacts')
            ->modalSubmitActionLabel('Send Messages')
            ->modalWidth('3xl');

        $this->form([
            Forms\Components\Section::make('Campaign Information')
                ->schema([
                    Forms\Components\TextInput::make('campaign_name')
                        ->label('Campaign Name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn () => 'Campaign ' . now()->format('Y-m-d H:i'))
                        ->helperText('Name for this bulk message campaign'),

                    Forms\Components\Placeholder::make('recipient_count')
                        ->label('Recipients')
                        ->content('Will be calculated from selection'),
                ])
                ->columns(2),

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
                        ->default(true)
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
                        ->required(fn (Forms\Get $get) => $get('use_template'))
                        ->live()
                        ->visible(fn (Forms\Get $get) => $get('use_template'))
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $template = Template::find($state);
                                if ($template) {
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
                        ->helperText('Default values for template variables. Individual records may have different values.')
                        ->visible(fn (Forms\Get $get) => $get('use_template') && $get('template_id'))
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
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
                        ->required(fn (Forms\Get $get) => !$get('use_template'))
                        ->visible(fn (Forms\Get $get) => !$get('use_template'))
                        ->live(debounce: 500)
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

            Forms\Components\Section::make('Scheduling & Delivery')
                ->schema([
                    Forms\Components\Toggle::make('schedule_message')
                        ->label('Schedule Message')
                        ->default(false)
                        ->live(),

                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Send At')
                        ->required(fn (Forms\Get $get) => $get('schedule_message'))
                        ->visible(fn (Forms\Get $get) => $get('schedule_message'))
                        ->minDate(now())
                        ->helperText('When to send the message'),

                    Forms\Components\Select::make('chunk_size')
                        ->label('Batch Size')
                        ->options([
                            50 => '50 messages per batch',
                            100 => '100 messages per batch',
                            250 => '250 messages per batch',
                            500 => '500 messages per batch',
                        ])
                        ->default(100)
                        ->helperText('Number of messages to send in each batch'),

                    Forms\Components\TextInput::make('delay_between_batches')
                        ->label('Delay Between Batches (seconds)')
                        ->numeric()
                        ->default(30)
                        ->minValue(0)
                        ->maxValue(3600)
                        ->helperText('Delay to prevent rate limiting'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Cost Estimation')
                ->schema([
                    Forms\Components\Placeholder::make('estimated_cost')
                        ->label('Total Estimated Cost')
                        ->content('Will be calculated based on recipients'),

                    Forms\Components\Placeholder::make('cost_breakdown')
                        ->label('Cost Breakdown')
                        ->content('Provider rates and message segments'),
                ])
                ->columns(2)
                ->collapsible(),
        ]);

        $this->action(function (array $data, Collection $records): void {
            $this->sendBulkMessages($data, $records);
        });

        // Pre-fill form with recipient data when the modal opens
        $this->fillForm(function (Collection $records): array {
            $recipientCount = $records->count();

            if ($recipientCount > $this->maxRecipients) {
                throw new \Exception("Too many recipients selected. Maximum {$this->maxRecipients} allowed.");
            }

            return [
                'recipient_count' => "{$recipientCount} contacts selected",
            ];
        });
    }

    protected function sendBulkMessages(array $data, Collection $records): void
    {
        try {
            $bulkService = app(BulkMessageService::class);
            $recipients = [];
            $invalidRecipients = [];

            // Prepare recipients list
            foreach ($records as $record) {
                $phone = $this->getPhoneNumber($record);
                if ($phone) {
                    $recipients[] = [
                        'phone' => $phone,
                        'name' => $this->getRecipientName($record),
                        'record_type' => get_class($record),
                        'record_id' => $record->getKey(),
                        'variables' => $this->extractVariablesFromRecord($record, $data),
                    ];
                } else {
                    $invalidRecipients[] = $record;
                }
            }

            if (empty($recipients)) {
                Notification::make()
                    ->title('No Valid Recipients')
                    ->body('No contacts with valid phone numbers found.')
                    ->warning()
                    ->send();
                return;
            }

            // Create batch record
            $batch = Batch::create([
                'name' => $data['campaign_name'],
                'total_recipients' => count($recipients),
                'provider' => $data['provider'],
                'channel' => $data['channel'],
                'status' => 'pending',
                'scheduled_at' => $data['schedule_message'] ? $data['scheduled_at'] : null,
                'metadata' => [
                    'template_id' => $data['template_id'] ?? null,
                    'use_template' => $data['use_template'],
                    'variables' => $data['variables'] ?? [],
                    'custom_message' => $data['custom_message'] ?? null,
                    'chunk_size' => $data['chunk_size'],
                    'delay_between_batches' => $data['delay_between_batches'],
                    'sent_by' => auth()->id(),
                    'source' => 'filament_bulk_action',
                ],
                'created_by' => auth()->id(),
            ]);

            // Send messages
            if ($data['schedule_message']) {
                // Schedule for later
                $result = $bulkService->scheduleBulkMessage($batch, $recipients);

                Notification::make()
                    ->title('Bulk Message Scheduled')
                    ->body("Messages scheduled for {$recipients} recipients at " . $data['scheduled_at'])
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view_batch')
                            ->label('View Batch')
                            ->url(route('filament.admin.resources.message-batches.view', ['record' => $batch]))
                            ->openUrlInNewTab(),
                    ])
                    ->send();
            } else {
                // Send immediately
                $result = $bulkService->sendBulkMessage($batch, $recipients);

                Notification::make()
                    ->title('Bulk Message Initiated')
                    ->body("Sending messages to " . count($recipients) . " recipients...")
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view_batch')
                            ->label('View Progress')
                            ->url(route('filament.admin.resources.message-batches.view', ['record' => $batch]))
                            ->openUrlInNewTab(),
                    ])
                    ->send();
            }

            // Warn about invalid recipients
            if (!empty($invalidRecipients)) {
                Notification::make()
                    ->title('Warning: Invalid Recipients')
                    ->body(count($invalidRecipients) . ' contacts were skipped due to missing phone numbers.')
                    ->warning()
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Bulk message action failed: ' . $e->getMessage(), [
                'data' => $data,
                'record_count' => $records->count(),
            ]);

            Notification::make()
                ->title('Error Sending Bulk Messages')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getPhoneNumber($record): ?string
    {
        return $record->{$this->phoneField};
    }

    protected function getRecipientName($record): ?string
    {
        if (!$this->nameField) {
            return null;
        }

        return $record->{$this->nameField} ?? $record->name ?? null;
    }

    protected function extractVariablesFromRecord($record, array $data): array
    {
        $variables = $data['variables'] ?? [];

        // Try to map common record fields to template variables
        $fieldMappings = [
            'name' => ['name', 'full_name', 'first_name'],
            'first_name' => ['first_name'],
            'last_name' => ['last_name'],
            'email' => ['email'],
            'phone' => ['phone', 'phone_number'],
        ];

        foreach ($fieldMappings as $templateVar => $fields) {
            if (isset($variables[$templateVar])) {
                foreach ($fields as $field) {
                    if (isset($record->{$field})) {
                        $variables[$templateVar] = $record->{$field};
                        break;
                    }
                }
            }
        }

        return $variables;
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

    public function maxRecipients(int $max): static
    {
        $this->maxRecipients = $max;
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
