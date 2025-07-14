<?php

namespace App\Messenger\Data;

use App\Messenger\Enums\MessageType;
use App\Messenger\Enums\MessageLanguage;
use App\Messenger\Enums\MessagePriority;

class SendMessageData
{
    public function __construct(
        public string $to,
        public string $message,
        public MessageType $type = MessageType::SMS,
        public ?string $provider = null,
        public MessageLanguage $language = MessageLanguage::ENGLISH,
        public MessagePriority $priority = MessagePriority::NORMAL,
        public array $variables = [],
        public ?string $templateKey = null,
        public ?int $templateId = null,
        public ?\DateTime $scheduledAt = null,
        public ?string $reference = null,
        public array $metadata = [],
        public ?string $from = null,
        public ?string $otpTemplate = null,
        public ?string $otpCode = null,
    ) {}

    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'message' => $this->message,
            'type' => $this->type->value,
            'provider' => $this->provider,
            'language' => $this->language->value,
            'priority' => $this->priority->value,
            'variables' => $this->variables,
            'template_key' => $this->templateKey,
            'template_id' => $this->templateId,
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'reference' => $this->reference,
            'metadata' => $this->metadata,
            'from' => $this->from,
            'otp_template' => $this->otpTemplate,
            'otp_code' => $this->otpCode,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            to: $data['to'],
            message: $data['message'],
            type: MessageType::from($data['type'] ?? 'sms'),
            provider: $data['provider'] ?? null,
            language: MessageLanguage::from($data['language'] ?? 'en'),
            priority: MessagePriority::from($data['priority'] ?? 5),
            variables: $data['variables'] ?? [],
            templateKey: $data['template_key'] ?? null,
            templateId: $data['template_id'] ?? null,
            scheduledAt: isset($data['scheduled_at']) ? new \DateTime($data['scheduled_at']) : null,
            reference: $data['reference'] ?? null,
            metadata: $data['metadata'] ?? [],
            from: $data['from'] ?? null,
            otpTemplate: $data['otp_template'] ?? null,
            otpCode: $data['otp_code'] ?? null,
        );
    }

    public function isOtp(): bool
    {
        return $this->type === MessageType::OTP || $this->otpCode !== null;
    }

    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null;
    }

    public function getQueuePriority(): int
    {
        return $this->priority->queuePriority();
    }
}
