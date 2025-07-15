<?php

namespace Ihabrouk\Messenger\Data;

use Ihabrouk\Messenger\Enums\MessageStatus;

class MessageResponse
{
    public function __construct(
        public bool $success,
        public MessageStatus $status,
        public ?string $providerId = null,
        public ?string $providerMessageId = null,
        public ?float $cost = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
        public ?string $provider = null,
        public ?\DateTime $sentAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status->value,
            'provider_id' => $this->providerId,
            'provider_message_id' => $this->providerMessageId,
            'cost' => $this->cost,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata,
            'provider' => $this->provider,
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s'),
        ];
    }

    public static function success(
        string $providerId,
        ?string $providerMessageId = null,
        ?float $cost = null,
        ?string $provider = null,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            status: MessageStatus::SENT,
            providerId: $providerId,
            providerMessageId: $providerMessageId,
            cost: $cost,
            provider: $provider,
            metadata: $metadata,
            sentAt: new \DateTime(),
        );
    }

    public static function failure(
        string $errorCode,
        string $errorMessage,
        ?string $provider = null,
        array $metadata = []
    ): self {
        return new self(
            success: false,
            status: MessageStatus::FAILED,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            provider: $provider,
            metadata: $metadata,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return !$this->success;
    }
}
