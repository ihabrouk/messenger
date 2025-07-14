<?php

namespace App\Messenger\Services;

use App\Messenger\Contracts\MessengerServiceInterface;
use App\Messenger\Data\SendMessageData;
use App\Messenger\Data\BulkMessageData;
use App\Messenger\Data\MessageResponse;
use App\Messenger\Models\Message;
use App\Messenger\Models\Template;
use App\Messenger\Models\Batch;
use App\Messenger\Enums\MessageStatus;
use App\Messenger\Enums\MessagePriority;
use App\Messenger\Exceptions\MessengerException;
use App\Messenger\Jobs\SendMessageJob;
use App\Messenger\Jobs\SendBulkMessageJob;
use App\Messenger\Jobs\ProcessScheduledMessageJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * MessengerService
 *
 * Core orchestration service for messaging operations
 * Handles sending, queuing, scheduling, and automation
 */
class MessengerService implements MessengerServiceInterface
{
    public function __construct(
        protected MessageProviderFactory $providerFactory,
        protected TemplateService $templateService,
        protected BulkMessageService $bulkService,
        protected DeliveryTrackingService $deliveryService,
        protected CircuitBreakerService $circuitBreaker
    ) {}

    /**
     * Send single message immediately
     */
    public function send(SendMessageData $data): MessageResponse
    {
        Log::info('MessengerService: Sending single message', [
            'recipient' => $data->to,
            'provider' => $data->provider,
            'channel' => $data->channel,
            'template' => $data->template_id,
        ]);

        // Check circuit breaker for provider
        if (!$this->circuitBreaker->isAvailable($data->provider)) {
            throw new MessengerException(
                "Provider {$data->provider} is currently unavailable (circuit breaker open)",
                ['provider' => $data->provider, 'circuit_breaker' => 'open']
            );
        }

        try {
            // Create message record
            $message = $this->createMessageRecord($data);

            // Get provider instance
            $provider = $this->providerFactory->make($data->provider, $data->channel);

            // Send message
            $response = $provider->send($data);

            // Update message with response
            $this->updateMessageFromResponse($message, $response);

            // Record success in circuit breaker
            $this->circuitBreaker->recordSuccess($data->provider);

            Log::info('Message sent successfully', [
                'message_id' => $message->id,
                'provider_message_id' => $response->providerMessageId,
                'status' => $response->status->value,
            ]);

            return $response;

        } catch (\Exception $e) {
            // Record failure in circuit breaker
            $this->circuitBreaker->recordFailure($data->provider);

            // Update message status if created
            if (isset($message)) {
                $message->update([
                    'status' => MessageStatus::FAILED,
                    'error_message' => $e->getMessage(),
                    'failed_at' => now(),
                ]);
            }

            Log::error('Message send failed', [
                'error' => $e->getMessage(),
                'recipient' => $data->to,
                'provider' => $data->provider,
            ]);

            throw $e;
        }
    }

    /**
     * Queue single message for background processing
     */
    public function queue(SendMessageData $data, ?\DateTimeInterface $delay = null): string
    {
        Log::info('MessengerService: Queuing message', [
            'recipient' => $data->to,
            'provider' => $data->provider,
            'delay' => $delay?->format('Y-m-d H:i:s'),
        ]);

        // Create message record
        $message = $this->createMessageRecord($data, MessageStatus::QUEUED);

        // Determine queue based on priority
        $queueName = $this->getQueueName($data->priority);

        // Dispatch job
        if ($delay) {
            SendMessageJob::dispatch($message->id)->delay($delay)->onQueue($queueName);
        } else {
            SendMessageJob::dispatch($message->id)->onQueue($queueName);
        }

        Log::info('Message queued successfully', [
            'message_id' => $message->id,
            'queue' => $queueName,
        ]);

        return $message->id;
    }

    /**
     * Send bulk messages
     */
    public function sendBulk(BulkMessageData $data): string
    {
        Log::info('MessengerService: Starting bulk send', [
            'recipient_count' => count($data->recipients),
            'provider' => $data->provider,
            'template' => $data->template_id,
        ]);

        return $this->bulkService->send($data);
    }

    /**
     * Schedule message for future delivery
     */
    public function schedule(SendMessageData $data, \DateTimeInterface $scheduledAt): string
    {
        Log::info('MessengerService: Scheduling message', [
            'recipient' => $data->to,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]);

        // Create message record with scheduled status
        $message = $this->createMessageRecord($data, MessageStatus::SCHEDULED);
        $message->update(['scheduled_at' => $scheduledAt]);

        // Queue scheduled job
        ProcessScheduledMessageJob::dispatch($message->id)
            ->delay($scheduledAt)
            ->onQueue('scheduled');

        return $message->id;
    }

    /**
     * Send message using template
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        array $variables = [],
        ?string $provider = null,
        ?string $channel = null
    ): MessageResponse {
        // Get template
        $template = $this->templateService->getByName($templateName);

        if (!$template) {
            throw new MessengerException("Template not found: {$templateName}");
        }

        // Determine provider and channel
        $provider = $provider ?? $this->getDefaultProvider($template);
        $channel = $channel ?? $this->getDefaultChannel($template);

        // Create send data
        $data = new SendMessageData(
            to: $to,
            body: null, // Will be rendered from template
            provider: $provider,
            channel: $channel,
            template_id: $template->id,
            variables: $variables
        );

        return $this->send($data);
    }

    /**
     * Retry failed message
     */
    public function retry(string $messageId): MessageResponse
    {
        $message = Message::findOrFail($messageId);

        if ($message->status !== MessageStatus::FAILED) {
            throw new MessengerException("Message is not in failed status: {$messageId}");
        }

        Log::info('Retrying failed message', ['message_id' => $messageId]);

        // Reset message status
        $message->update([
            'status' => MessageStatus::PENDING,
            'error_message' => null,
            'failed_at' => null,
            'retry_count' => $message->retry_count + 1,
        ]);

        // Create new send data from message
        $data = $this->createSendDataFromMessage($message);

        return $this->send($data);
    }

    /**
     * Get message status
     */
    public function getStatus(string $messageId): MessageStatus
    {
        $message = Message::findOrFail($messageId);
        return $message->status;
    }

    /**
     * Cancel scheduled message
     */
    public function cancel(string $messageId): bool
    {
        $message = Message::findOrFail($messageId);

        if (!in_array($message->status, [MessageStatus::SCHEDULED, MessageStatus::QUEUED])) {
            throw new MessengerException("Cannot cancel message in status: {$message->status->value}");
        }

        $message->update([
            'status' => MessageStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);

        Log::info('Message cancelled', ['message_id' => $messageId]);

        return true;
    }

    /**
     * Get provider health status
     */
    public function getProviderHealth(): array
    {
        $providers = config('messenger.providers', []);
        $health = [];

        foreach (array_keys($providers) as $provider) {
            $health[$provider] = [
                'available' => $this->circuitBreaker->isAvailable($provider),
                'status' => $this->circuitBreaker->getStatus($provider),
                'failure_count' => $this->circuitBreaker->getFailureCount($provider),
                'last_failure' => $this->circuitBreaker->getLastFailureTime($provider),
            ];
        }

        return $health;
    }

    /**
     * Create message record
     */
    protected function createMessageRecord(
        SendMessageData $data,
        MessageStatus $status = MessageStatus::PENDING
    ): Message {
        // Render template if provided
        $body = $data->body;
        if ($data->template_id) {
            $template = Template::findOrFail($data->template_id);
            $body = $this->templateService->render($template, $data->variables);
        }

        return Message::create([
            'to' => $data->to,
            'body' => $body,
            'provider' => $data->provider,
            'channel' => $data->channel,
            'status' => $status,
            'template_id' => $data->template_id,
            'variables' => $data->variables,
            'priority' => $data->priority,
            'scheduled_at' => $data->scheduled_at,
            'messageable_type' => $data->messageable_type,
            'messageable_id' => $data->messageable_id,
        ]);
    }

    /**
     * Update message from provider response
     */
    protected function updateMessageFromResponse(Message $message, MessageResponse $response): void
    {
        $message->update([
            'status' => $response->status,
            'provider_message_id' => $response->providerMessageId,
            'cost' => $response->cost,
            'sent_at' => $response->sentAt ?? now(),
            'delivery_status' => $response->deliveryStatus,
            'error_message' => $response->errorMessage,
        ]);
    }

    /**
     * Create SendMessageData from Message record
     */
    protected function createSendDataFromMessage(Message $message): SendMessageData
    {
        return new SendMessageData(
            to: $message->to,
            body: $message->body,
            provider: $message->provider,
            channel: $message->channel,
            template_id: $message->template_id,
            variables: $message->variables ?? [],
            priority: $message->priority ?? MessagePriority::NORMAL,
            messageable_type: $message->messageable_type,
            messageable_id: $message->messageable_id
        );
    }

    /**
     * Get queue name based on priority
     */
    protected function getQueueName(MessagePriority $priority): string
    {
        return match($priority) {
            MessagePriority::URGENT => 'urgent',
            MessagePriority::HIGH => 'high',
            MessagePriority::NORMAL => 'default',
            MessagePriority::LOW => 'low',
        };
    }

    /**
     * Get default provider for template
     */
    protected function getDefaultProvider(Template $template): string
    {
        // Check template settings first
        if (isset($template->settings['preferred_provider'])) {
            return $template->settings['preferred_provider'];
        }

        // Use system default
        return config('messenger.default_provider', 'sms_misr');
    }

    /**
     * Get default channel for template
     */
    protected function getDefaultChannel(Template $template): string
    {
        $channels = $template->channels ?? ['sms'];
        return $channels[0] ?? 'sms';
    }
}
