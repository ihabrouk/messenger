<?php

namespace Ihabrouk\Messenger\Data;

use DateTime;

class BulkMessageData
{
    public function __construct(
        public array $recipients,
        public string $message,
        public ?string $templateKey = null,
        public array $variables = [],
        public ?string $provider = null,
        public ?string $channel = null,
        public ?DateTime $scheduledAt = null,
        public ?string $campaignName = null,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'recipients' => $this->recipients,
            'message' => $this->message,
            'template_key' => $this->templateKey,
            'variables' => $this->variables,
            'provider' => $this->provider,
            'channel' => $this->channel,
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'campaign_name' => $this->campaignName,
            'metadata' => $this->metadata,
        ];
    }

    public function getRecipientCount(): int
    {
        return count($this->recipients);
    }

    public function chunk(int $size): array
    {
        return array_chunk($this->recipients, $size);
    }

    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null;
    }
}
