<?php

namespace Ihabrouk\Messenger\Services;

use Ihabrouk\Messenger\Models\Batch;
use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Models\Template;
use Ihabrouk\Messenger\Services\MessageProviderFactory;
use Ihabrouk\Messenger\Data\SendMessageData;
use Ihabrouk\Messenger\Data\BulkMessageData;
use Ihabrouk\Messenger\Enums\MessageType;
use Ihabrouk\Messenger\Enums\MessageStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;

class BulkMessageService
{
    public function __construct(
        protected MessageProviderFactory $providerFactory,
        protected TemplateService $templateService
    ) {}

    /**
     * Send bulk messages immediately
     */
    public function sendBulkMessage(Batch $batch, array $recipients): array
    {
        try {
            DB::beginTransaction();

            // Update batch status
            $batch->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Process recipients in chunks
            $chunkSize = $batch->metadata['chunk_size'] ?? 100;
            $delay = $batch->metadata['delay_between_batches'] ?? 30;
            $results = [];

            $chunks = array_chunk($recipients, $chunkSize);

            foreach ($chunks as $index => $chunk) {
                // Add delay between chunks (except first)
                if ($index > 0) {
                    sleep($delay);
                }

                $chunkResults = $this->processChunk($batch, $chunk);
                $results = array_merge($results, $chunkResults);

                // Update batch statistics
                $this->updateBatchStats($batch);
            }

            // Mark batch as completed
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            DB::commit();

            Log::info('Bulk message batch completed', [
                'batch_id' => $batch->id,
                'recipients' => count($recipients),
                'chunks' => count($chunks),
            ]);

            return $results;

        } catch (\Exception $e) {
            DB::rollBack();

            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'metadata' => array_merge($batch->metadata ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]),
            ]);

            Log::error('Bulk message batch failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Schedule bulk messages for later
     */
    public function scheduleBulkMessage(Batch $batch, array $recipients): array
    {
        try {
            // For now, we'll just update the batch status
            // In a full implementation, this would schedule jobs

            $batch->update([
                'status' => 'scheduled',
                'metadata' => array_merge($batch->metadata ?? [], [
                    'scheduled_recipients' => count($recipients),
                    'scheduled_at' => now()->toISOString(),
                ]),
            ]);

            // TODO: Implement actual job scheduling
            // \Ihabrouk\Messenger\Jobs\ProcessScheduledBatchJob::dispatch($batch, $recipients)
            //     ->delay($batch->scheduled_at);

            Log::info('Bulk message batch scheduled', [
                'batch_id' => $batch->id,
                'recipients' => count($recipients),
                'scheduled_for' => $batch->scheduled_at,
            ]);

            return ['status' => 'scheduled', 'batch_id' => $batch->id];

        } catch (\Exception $e) {
            $batch->update(['status' => 'failed']);

            Log::error('Failed to schedule bulk message batch', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Start a pending batch
     */
    public function startBatch(Batch $batch): void
    {
        if ($batch->status !== 'pending') {
            throw new \Exception("Cannot start batch with status: {$batch->status}");
        }

        // For now, we'll mark it as processing
        // In a full implementation, this would dispatch jobs

        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        // TODO: Implement actual job dispatching
        // \Ihabrouk\Messenger\Jobs\ProcessBatchJob::dispatch($batch);

        Log::info('Batch started', ['batch_id' => $batch->id]);
    }

    /**
     * Process a chunk of recipients
     */
    protected function processChunk(Batch $batch, array $recipients): array
    {
        $results = [];
        $metadata = $batch->metadata ?? [];

        foreach ($recipients as $recipient) {
            try {
                // Prepare message data
                // Create proper SendMessageData object
                $sendMessageData = new SendMessageData(
                    to: $recipient['phone'],
                    message: $metadata['use_template'] ? '' : $metadata['custom_message'],
                    type: MessageType::SMS,
                    provider: $batch->provider,
                    templateId: $metadata['use_template'] && isset($metadata['template_id']) ? $metadata['template_id'] : null,
                    variables: $metadata['use_template'] ? array_merge(
                        $metadata['variables'] ?? [],
                        $recipient['variables'] ?? []
                    ) : [],
                    metadata: [
                        'batch_id' => $batch->id,
                        'recipient_name' => $recipient['name'] ?? null,
                        'source' => 'bulk_campaign',
                    ]
                );

                // Send the message directly using provider
                $result = $this->sendMessage($sendMessageData);

                if ($result->isSuccessful()) {
                    $results[] = ['recipient' => $recipient['phone'], 'status' => 'sent', 'message_id' => $result->providerMessageId];
                } else {
                    $results[] = ['recipient' => $recipient['phone'], 'status' => 'failed', 'error' => $result->errorMessage];
                }

            } catch (\Exception $e) {
                $results[] = ['recipient' => $recipient['phone'], 'status' => 'failed', 'error' => $e->getMessage()];

                Log::error('Failed to send message in bulk chunk', [
                    'batch_id' => $batch->id,
                    'recipient' => $recipient['phone'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Update batch statistics
     */
    protected function updateBatchStats(Batch $batch): void
    {
        $messages = $batch->messages();

        $stats = [
            'sent_count' => $messages->where('status', MessageStatus::SENT)->count(),
            'delivered_count' => $messages->where('status', MessageStatus::DELIVERED)->count(),
            'failed_count' => $messages->where('status', MessageStatus::FAILED)->count(),
            'total_cost' => $messages->sum('cost'),
        ];

        $batch->update($stats);
    }

    /**
     * Calculate cost estimation for bulk message
     */
    public function estimateBulkCost(array $recipients, ?int $templateId = null, ?string $customMessage = null, string $provider = 'smsmisr', string $channel = 'sms'): array
    {
        $totalCost = 0;
        $messageLength = 0;

        // Get message content for length calculation
        if ($templateId) {
            $template = Template::find($templateId);
            $messageLength = mb_strlen($template ? $template->body : '');
        } else {
            $messageLength = mb_strlen($customMessage ?? '');
        }

        // Calculate segments
        $segments = $channel === 'sms' ? ceil($messageLength / 160) : 1;

        // Calculate cost per message
        $costPerMessage = match([$provider, $channel]) {
            ['smsmisr', 'sms'] => $segments * 0.05,
            ['twilio', 'sms'] => $segments * 0.075,
            ['twilio', 'whatsapp'] => 0.05,
            default => $segments * 0.01,
        };

        $totalCost = count($recipients) * $costPerMessage;

        return [
            'recipient_count' => count($recipients),
            'message_length' => $messageLength,
            'segments_per_message' => $segments,
            'cost_per_message' => $costPerMessage,
            'total_cost' => $totalCost,
            'provider' => $provider,
            'channel' => $channel,
        ];
    }

    /**
     * Get batch delivery statistics
     */
    public function getBatchStats(Batch $batch): array
    {
        $messages = $batch->messages();

        return [
            'total_recipients' => $batch->total_recipients,
            'sent_count' => $messages->where('status', MessageStatus::SENT)->count(),
            'delivered_count' => $messages->where('status', MessageStatus::DELIVERED)->count(),
            'failed_count' => $messages->where('status', MessageStatus::FAILED)->count(),
            'pending_count' => $messages->where('status', MessageStatus::PENDING)->count(),
            'total_cost' => $messages->sum('cost'),
            'average_cost' => $messages->avg('cost'),
            'delivery_rate' => $batch->total_recipients > 0
                ? round(($messages->where('status', MessageStatus::DELIVERED)->count() / $batch->total_recipients) * 100, 2)
                : 0,
        ];
    }

    /**
     * Cancel a batch
     */
    public function cancelBatch(Batch $batch): void
    {
        if (!in_array($batch->status, ['pending', 'processing', 'scheduled'])) {
            throw new \Exception("Cannot cancel batch with status: {$batch->status}");
        }

        DB::beginTransaction();

        try {
            // Cancel pending messages
            $batch->messages()
                ->where('status', MessageStatus::PENDING)
                ->update(['status' => MessageStatus::CANCELLED]);

            // Update batch status
            $batch->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

            DB::commit();

            Log::info('Batch cancelled', ['batch_id' => $batch->id]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Retry failed messages in a batch
     */
    public function retryFailedMessages(Batch $batch): array
    {
        $failedMessages = $batch->messages()
            ->where('status', MessageStatus::FAILED)
            ->get();

        if ($failedMessages->isEmpty()) {
            return ['status' => 'no_failed_messages', 'count' => 0];
        }

        $retryCount = 0;
        $results = [];

        foreach ($failedMessages as $message) {
            try {
                // Create proper SendMessageData object for retry
                $sendMessageData = new SendMessageData(
                    to: $message->recipient_phone,
                    message: $message->template_id ? '' : $message->body,
                    type: MessageType::SMS,
                    provider: $message->provider,
                    templateId: $message->template_id,
                    variables: $message->variables ?? [],
                    metadata: array_merge($message->metadata ?? [], [
                        'retry_of' => $message->id,
                        'retry_batch_id' => $batch->id,
                        'retried_at' => now()->toISOString(),
                    ])
                );

                $result = $this->sendMessage($sendMessageData);

                if ($result->isSuccessful()) {
                    $message->update(['status' => MessageStatus::SENT]);
                    $retryCount++;
                    $results[] = ['message_id' => $message->id, 'status' => 'retried'];
                } else {
                    $results[] = ['message_id' => $message->id, 'status' => 'retry_failed', 'error' => $result->errorMessage];
                }

            } catch (\Exception $e) {
                $results[] = ['message_id' => $message->id, 'status' => 'retry_failed', 'error' => $e->getMessage()];
            }
        }

        // Update batch statistics
        $this->updateBatchStats($batch);

        Log::info('Batch retry completed', [
            'batch_id' => $batch->id,
            'failed_messages' => $failedMessages->count(),
            'retried_successfully' => $retryCount,
        ]);

        return [
            'status' => 'completed',
            'total_failed' => $failedMessages->count(),
            'retried_successfully' => $retryCount,
            'results' => $results,
        ];
    }

    /**
     * Send a message using the appropriate provider
     */
    protected function sendMessage(SendMessageData $data): \Ihabrouk\Messenger\Data\MessageResponse
    {
        $provider = $this->providerFactory->make($data->provider);
        
        return $provider->send($data);
    }
}
