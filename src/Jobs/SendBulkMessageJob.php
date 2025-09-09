<?php

namespace Ihabrouk\Messenger\Jobs;

use Exception;
use Throwable;
use Ihabrouk\Messenger\Models\Batch;
use Ihabrouk\Messenger\Services\BulkMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendBulkMessageJob
 *
 * Background job for processing bulk message campaigns
 * Handles batch processing with chunking and progress tracking
 */
class SendBulkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Don't retry bulk jobs
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public string $batchId
    ) {}

    public function handle(BulkMessageService $bulkService): void
    {
        $batch = Batch::find($this->batchId);

        if (!$batch) {
            Log::warning('SendBulkMessageJob: Batch not found', ['batch_id' => $this->batchId]);
            return;
        }

        Log::info('SendBulkMessageJob: Processing batch', [
            'batch_id' => $this->batchId,
            'total_recipients' => $batch->total_recipients,
            'provider' => $batch->provider,
        ]);

        try {
            $bulkService->processBatch($batch);

            Log::info('SendBulkMessageJob: Batch processed successfully', [
                'batch_id' => $this->batchId,
                'sent_count' => $batch->fresh()->sent_count,
            ]);

        } catch (Exception $e) {
            Log::error('SendBulkMessageJob: Batch processing failed', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);

            $batch->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $batch = Batch::find($this->batchId);

        if ($batch) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
            ]);

            Log::error('SendBulkMessageJob: Final failure', [
                'batch_id' => $this->batchId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        $batch = Batch::find($this->batchId);

        return [
            'messenger:bulk',
            'provider:' . ($batch?->provider ?? 'unknown'),
            'channel:' . ($batch?->channel ?? 'unknown'),
            'recipients:' . ($batch?->total_recipients ?? 0),
        ];
    }
}
