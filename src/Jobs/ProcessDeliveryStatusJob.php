<?php

namespace Ihabrouk\Messenger\Jobs;

use Ihabrouk\Messenger\Models\Webhook;
use Ihabrouk\Messenger\Services\DeliveryTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessDeliveryStatusJob
 *
 * Background job for processing delivery status webhooks
 * Updates message status based on provider callbacks
 */
class ProcessDeliveryStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public string $webhookId
    ) {}

    public function handle(DeliveryTrackingService $deliveryService): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (!$webhook) {
            Log::warning('ProcessDeliveryStatusJob: Webhook not found', ['webhook_id' => $this->webhookId]);
            return;
        }

        // Skip if already processed
        if ($webhook->processed) {
            Log::info('ProcessDeliveryStatusJob: Webhook already processed', ['webhook_id' => $this->webhookId]);
            return;
        }

        Log::info('ProcessDeliveryStatusJob: Processing webhook', [
            'webhook_id' => $this->webhookId,
            'provider' => $webhook->provider,
        ]);

        try {
            $result = $deliveryService->processWebhook(
                $webhook->provider,
                $webhook->payload,
                $webhook->headers ?? []
            );

            $webhook->update([
                'processed' => true,
                'processed_at' => now(),
                'result' => $result,
            ]);

            Log::info('ProcessDeliveryStatusJob: Webhook processed successfully', [
                'webhook_id' => $this->webhookId,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            $webhook->update([
                'processed' => false,
                'error' => $e->getMessage(),
            ]);

            Log::error('ProcessDeliveryStatusJob: Webhook processing failed', [
                'webhook_id' => $this->webhookId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $webhook = Webhook::find($this->webhookId);

        if ($webhook) {
            $webhook->update([
                'processed' => false,
                'error' => $exception->getMessage(),
            ]);

            Log::error('ProcessDeliveryStatusJob: Final failure', [
                'webhook_id' => $this->webhookId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        $webhook = Webhook::find($this->webhookId);

        return [
            'messenger:webhook',
            'provider:' . ($webhook?->provider ?? 'unknown'),
        ];
    }
}
