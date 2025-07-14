<?php

namespace App\Messenger\Services;

use App\Messenger\Models\Batch;
use App\Messenger\Models\Message;
use App\Messenger\Models\Template;
use App\Messenger\Services\MessengerService;
use App\Messenger\Data\SendMessageData;
use App\Messenger\Data\BulkMessageData;
use App\Messenger\Enums\MessageType;
use App\Messenger\Enums\MessageStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;

class BulkMessageService
{
    public function __construct(
        protected MessengerService $messengerService,
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
            // \App\Messenger\Jobs\ProcessScheduledBatchJob::dispatch($batch, $recipients)
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
        // \App\Messenger\Jobs\ProcessBatchJob::dispatch($batch);

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
                $messageData = [
                    'recipient_phone' => $recipient['phone'],
                    'provider' => $batch->provider,
                    'channel' => $batch->channel,
                    'type' => 'transactional', // Default type
                    'metadata' => [
                        'batch_id' => $batch->id,
                        'recipient_name' => $recipient['name'] ?? null,
                        'source' => 'bulk_campaign',
                    ],
                ];

                // Handle template or custom message
                if ($metadata['use_template'] && isset($metadata['template_id'])) {
                    $messageData['template_id'] = $metadata['template_id'];
                    $messageData['variables'] = array_merge(
                        $metadata['variables'] ?? [],
                        $recipient['variables'] ?? []
                    );
                } else {
                    $messageData['message'] = $metadata['custom_message'];
                }

                // Send the message
                $result = $this->messengerService->send($messageData);

                if ($result->isSuccessful()) {
                    $results[] = ['recipient' => $recipient['phone'], 'status' => 'sent', 'message_id' => $result->getMessageId()];
                } else {
                    $results[] = ['recipient' => $recipient['phone'], 'status' => 'failed', 'error' => $result->getErrorMessage()];
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
                $messageData = [
                    'recipient_phone' => $message->recipient_phone,
                    'provider' => $message->provider,
                    'channel' => $message->channel,
                    'type' => $message->type,
                    'metadata' => array_merge($message->metadata ?? [], [
                        'retry_of' => $message->id,
                        'retry_batch_id' => $batch->id,
                        'retried_at' => now()->toISOString(),
                    ]),
                ];

                if ($message->template_id) {
                    $messageData['template_id'] = $message->template_id;
                    $messageData['variables'] = $message->variables ?? [];
                } else {
                    $messageData['message'] = $message->body;
                }

                $result = $this->messengerService->send($messageData);

                if ($result->isSuccessful()) {
                    $message->update(['status' => MessageStatus::SENT]);
                    $retryCount++;
                    $results[] = ['message_id' => $message->id, 'status' => 'retried'];
                } else {
                    $results[] = ['message_id' => $message->id, 'status' => 'retry_failed', 'error' => $result->getErrorMessage()];
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
}
