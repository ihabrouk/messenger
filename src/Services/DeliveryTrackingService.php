<?php

namespace Ihabrouk\Messenger\Services;

use InvalidArgumentException;
use Exception;
use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Models\Webhook;
use Ihabrouk\Messenger\Enums\MessageStatus;
use Ihabrouk\Messenger\Events\MessageDelivered;
use Ihabrouk\Messenger\Events\MessageFailed;
use Ihabrouk\Messenger\Events\MessageBounced;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

/**
 * DeliveryTrackingService
 *
 * Processes provider callbacks and updates delivery status
 * Handles webhooks from SMS Misr, Twilio, and other providers
 */
class DeliveryTrackingService
{
    /**
     * Process webhook payload from provider
     */
    public function processWebhook(string $provider, array $payload, array $headers = []): bool
    {
        Log::info('Processing delivery webhook', [
            'provider' => $provider,
            'payload_keys' => array_keys($payload),
        ]);

        try {
            // Store webhook record
            $webhook = $this->storeWebhook($provider, $payload, $headers);

            // Process based on provider
            $result = match ($provider) {
                'sms_misr' => $this->processSMSMisrWebhook($payload),
                'twilio' => $this->processTwilioWebhook($payload),
                'mocktest' => $this->processMockTestWebhook($payload),
                default => throw new InvalidArgumentException("Unsupported provider: {$provider}")
            };

            // Update webhook status
            $webhook->update([
                'processed' => true,
                'processed_at' => now(),
                'result' => $result,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            // Update webhook with error
            if (isset($webhook)) {
                $webhook->update([
                    'processed' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }

    /**
     * Process SMS Misr webhook
     */
    protected function processSMSMisrWebhook(array $payload): bool
    {
        // SMS Misr webhook format:
        // {
        //   "SMSID": "123456",
        //   "Status": "Delivered",
        //   "Mobile": "+201234567890",
        //   "DeliveredAt": "2024-01-01 12:00:00"
        // }

        $smsId = $payload['SMSID'] ?? null;
        $status = $payload['Status'] ?? null;
        $mobile = $payload['Mobile'] ?? null;
        $deliveredAt = $payload['DeliveredAt'] ?? null;

        if (!$smsId || !$status) {
            Log::warning('Invalid SMS Misr webhook payload', $payload);
            return false;
        }

        // Find message by provider message ID
        $message = Message::where('provider_message_id', $smsId)
            ->where('provider', 'sms_misr')
            ->first();

        if (!$message) {
            Log::warning('Message not found for SMS Misr webhook', [
                'sms_id' => $smsId,
                'mobile' => $mobile,
            ]);
            return false;
        }

        // Map SMS Misr status to our status
        $messageStatus = $this->mapSMSMisrStatus($status);

        return $this->updateMessageStatus($message, $messageStatus, [
            'delivery_status' => $status,
            'delivered_at' => $deliveredAt ? Carbon::parse($deliveredAt) : null,
        ]);
    }

    /**
     * Process Twilio webhook
     */
    protected function processTwilioWebhook(array $payload): bool
    {
        // Twilio webhook format:
        // {
        //   "MessageSid": "SM1234567890abcdef",
        //   "MessageStatus": "delivered",
        //   "To": "+201234567890",
        //   "From": "+1234567890",
        //   "ErrorCode": null,
        //   "ErrorMessage": null
        // }

        $messageSid = $payload['MessageSid'] ?? null;
        $messageStatus = $payload['MessageStatus'] ?? null;
        $to = $payload['To'] ?? null;
        $errorCode = $payload['ErrorCode'] ?? null;
        $errorMessage = $payload['ErrorMessage'] ?? null;

        if (!$messageSid || !$messageStatus) {
            Log::warning('Invalid Twilio webhook payload', $payload);
            return false;
        }

        // Find message by provider message ID
        $message = Message::where('provider_message_id', $messageSid)
            ->where('provider', 'twilio')
            ->first();

        if (!$message) {
            Log::warning('Message not found for Twilio webhook', [
                'message_sid' => $messageSid,
                'to' => $to,
            ]);
            return false;
        }

        // Map Twilio status to our status
        $status = $this->mapTwilioStatus($messageStatus);

        return $this->updateMessageStatus($message, $status, [
            'delivery_status' => $messageStatus,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'delivered_at' => in_array($messageStatus, ['delivered', 'read']) ? now() : null,
        ]);
    }

    /**
     * Process MockTest webhook
     */
    protected function processMockTestWebhook(array $payload): bool
    {
        $messageId = $payload['message_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$messageId || !$status) {
            return false;
        }

        $message = Message::where('provider_message_id', $messageId)
            ->where('provider', 'mocktest')
            ->first();

        if (!$message) {
            return false;
        }

        $messageStatus = MessageStatus::from($status);

        return $this->updateMessageStatus($message, $messageStatus, [
            'delivery_status' => $status,
            'delivered_at' => $status === 'delivered' ? now() : null,
        ]);
    }

    /**
     * Update message status and fire events
     */
    protected function updateMessageStatus(
        Message $message,
        MessageStatus $status,
        array $additionalData = []
    ): bool {
        $oldStatus = $message->status;

        // Update message
        $updateData = array_merge([
            'status' => $status,
        ], $additionalData);

        $message->update($updateData);

        // Fire appropriate events
        $this->fireStatusEvent($message, $oldStatus, $status);

        Log::info('Message status updated', [
            'message_id' => $message->id,
            'old_status' => $oldStatus->value,
            'new_status' => $status->value,
            'provider' => $message->provider,
        ]);

        return true;
    }

    /**
     * Fire status change event
     */
    protected function fireStatusEvent(Message $message, MessageStatus $oldStatus, MessageStatus $newStatus): void
    {
        // Don't fire events for same status
        if ($oldStatus === $newStatus) {
            return;
        }

        match ($newStatus) {
            MessageStatus::DELIVERED => Event::dispatch(new MessageDelivered($message)),
            MessageStatus::FAILED => Event::dispatch(new MessageFailed($message)),
            MessageStatus::BOUNCED => Event::dispatch(new MessageBounced($message)),
            default => null, // No specific event for other statuses
        };
    }

    /**
     * Store webhook record
     */
    protected function storeWebhook(string $provider, array $payload, array $headers): Webhook
    {
        return Webhook::create([
            'provider' => $provider,
            'payload' => $payload,
            'headers' => $headers,
            'processed' => false,
            'signature_valid' => true, // Assume valid if we got here
        ]);
    }

    /**
     * Map SMS Misr status to our status enum
     */
    protected function mapSMSMisrStatus(string $status): MessageStatus
    {
        return match (strtolower($status)) {
            'delivered' => MessageStatus::DELIVERED,
            'failed', 'not delivered' => MessageStatus::FAILED,
            'queued', 'pending' => MessageStatus::PENDING,
            'sent' => MessageStatus::SENT,
            'expired' => MessageStatus::FAILED,
            'rejected' => MessageStatus::BOUNCED,
            default => MessageStatus::UNKNOWN,
        };
    }

    /**
     * Map Twilio status to our status enum
     */
    protected function mapTwilioStatus(string $status): MessageStatus
    {
        return match (strtolower($status)) {
            'delivered', 'read' => MessageStatus::DELIVERED,
            'failed', 'undelivered' => MessageStatus::FAILED,
            'queued', 'accepted' => MessageStatus::PENDING,
            'sending', 'sent' => MessageStatus::SENT,
            'canceled' => MessageStatus::CANCELLED,
            'rejected' => MessageStatus::BOUNCED,
            default => MessageStatus::UNKNOWN,
        };
    }

    /**
     * Get delivery statistics
     */
    public function getDeliveryStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $stats = Message::where('created_at', '>=', $startDate)
            ->selectRaw('
                status,
                provider,
                COUNT(*) as count,
                AVG(cost) as avg_cost,
                SUM(cost) as total_cost
            ')
            ->groupBy(['status', 'provider'])
            ->get();

        $summary = [
            'total_messages' => $stats->sum('count'),
            'total_cost' => $stats->sum('total_cost'),
            'avg_cost' => $stats->avg('avg_cost'),
            'by_status' => [],
            'by_provider' => [],
        ];

        foreach ($stats as $stat) {
            $summary['by_status'][$stat->status] = ($summary['by_status'][$stat->status] ?? 0) + $stat->count;
            $summary['by_provider'][$stat->provider] = ($summary['by_provider'][$stat->provider] ?? 0) + $stat->count;
        }

        // Calculate delivery rate
        $delivered = $summary['by_status'][MessageStatus::DELIVERED->value] ?? 0;
        $total = $summary['total_messages'];
        $summary['delivery_rate'] = $total > 0 ? round(($delivered / $total) * 100, 2) : 0;

        return $summary;
    }

    /**
     * Get recent delivery events
     */
    public function getRecentEvents(int $limit = 50): array
    {
        return Message::with(['template', 'batch'])
            ->whereNotNull('delivered_at')
            ->orWhereNotNull('failed_at')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Message $message) {
                return [
                    'id' => $message->id,
                    'to' => $message->to,
                    'status' => $message->status->value,
                    'provider' => $message->provider,
                    'channel' => $message->channel,
                    'template' => $message->template?->display_name,
                    'cost' => $message->cost,
                    'delivered_at' => $message->delivered_at?->toISOString(),
                    'failed_at' => $message->failed_at?->toISOString(),
                    'error_message' => $message->error_message,
                ];
            })
            ->toArray();
    }
}
