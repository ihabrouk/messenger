<?php

namespace Ihabrouk\Messenger\Components;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Ihabrouk\Messenger\Models\Template;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\KeyValue;

class TemplateSelector extends Component
{
    protected string $view = 'messenger.components.template-selector';

    protected ?string $channel = null;
    protected ?string $category = null;
    protected bool $showPreview = true;
    protected bool $showVariables = true;

    public static function make(string $name = 'template_selector'): static
    {
        return parent::make($name);
    }

    public function channel(?string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function category(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function showPreview(bool $show = true): static
    {
        $this->showPreview = $show;
        return $this;
    }

    public function showVariables(bool $show = true): static
    {
        $this->showVariables = $show;
        return $this;
    }

    public function getChildComponents(): array
    {
        return [
            Section::make('Template Selection')
                ->schema([
                    Select::make('template_id')
                        ->label('Message Template')
                        ->options(function (Get $get) {
                            $query = Template::where('is_active', true)
                                ->where('approval_status', 'approved');

                            if ($this->channel) {
                                $query->whereJsonContains('channels', $this->channel);
                            }

                            if ($this->category) {
                                $query->where('category', $this->category);
                            }

                            return $query->get()->pluck('display_name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                $template = Template::find($state);
                                if ($template) {
                                    // Set template variables with empty values
                                    $variables = [];
                                    foreach ($template->variables ?? [] as $var) {
                                        $variables[$var] = '';
                                    }
                                    $set('template_variables', $variables);
                                    $set('template_body', $template->body);
                                    $set('template_preview', $template->body);
                                }
                            } else {
                                $set('template_variables', []);
                                $set('template_body', '');
                                $set('template_preview', '');
                            }
                        })
                        ->helperText('Select an approved template for your message'),

                    KeyValue::make('template_variables')
                        ->label('Template Variables')
                        ->visible(fn (Get $get) => !empty($get('template_variables')) && $this->showVariables)
                        ->live()
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            $templateBody = $get('template_body');
                            if ($templateBody && $state) {
                                $preview = $templateBody;
                                foreach ($state as $key => $value) {
                                    $preview = str_replace("{{ {$key} }}", $value ?: "[{$key}]", $preview);
                                }
                                $set('template_preview', $preview);
                            }
                        })
                        ->helperText('Provide values for template variables'),
                ])
                ->columns(1),

            Section::make('Preview')
                ->schema([
                    Placeholder::make('template_preview')
                        ->label('Message Preview')
                        ->content(function (Get $get) {
                            $preview = $get('template_preview');
                            if (!$preview) {
                                return 'Select a template to see preview...';
                            }
                            return $preview;
                        })
                        ->columnSpanFull(),

                    Placeholder::make('character_count')
                        ->label('Character Count')
                        ->content(function (Get $get) {
                            $preview = $get('template_preview');
                            if (!$preview) {
                                return '0 characters';
                            }
                            $count = mb_strlen($preview);
                            $segments = ceil($count / 160);
                            return "{$count} characters ({$segments} SMS segment" . ($segments !== 1 ? 's' : '') . ')';
                        }),

                    Placeholder::make('estimated_cost')
                        ->label('Estimated Cost')
                        ->content(function (Get $get) {
                            $preview = $get('template_preview');
                            if (!$preview) {
                                return '$0.00';
                            }
                            $segments = ceil(mb_strlen($preview) / 160);
                            $cost = $segments * 0.05; // Basic cost estimation
                            return '$' . number_format($cost, 3);
                        }),
                ])
                ->visible($this->showPreview)
                ->columns(3)
                ->collapsible(),
        ];
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function shouldShowPreview(): bool
    {
        return $this->showPreview;
    }

    public function shouldShowVariables(): bool
    {
        return $this->showVariables;
    }
}
