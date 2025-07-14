<?php

namespace App\Messenger\Contracts;

use App\Messenger\Data\SendMessageData;
use App\Messenger\Data\BulkMessageData;
use App\Messenger\Data\MessageResponse;
use App\Messenger\Enums\MessageStatus;

/**
 * MessengerServiceInterface
 *
 * Core contract for messaging operations
 */
interface MessengerServiceInterface
{
    /**
     * Send single message immediately
     */
    public function send(SendMessageData $data): MessageResponse;

    /**
     * Queue single message for background processing
     */
    public function queue(SendMessageData $data, ?\DateTimeInterface $delay = null): string;

    /**
     * Send bulk messages
     */
    public function sendBulk(BulkMessageData $data): string;

    /**
     * Schedule message for future delivery
     */
    public function schedule(SendMessageData $data, \DateTimeInterface $scheduledAt): string;

    /**
     * Send message using template
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        array $variables = [],
        ?string $provider = null,
        ?string $channel = null
    ): MessageResponse;

    /**
     * Retry failed message
     */
    public function retry(string $messageId): MessageResponse;

    /**
     * Get message status
     */
    public function getStatus(string $messageId): MessageStatus;

    /**
     * Cancel scheduled message
     */
    public function cancel(string $messageId): bool;

    /**
     * Get provider health status
     */
    public function getProviderHealth(): array;
}
