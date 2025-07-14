<?php

namespace App\Messenger\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Messenger\Services\MessageProviderFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SmsMisrWebhookController extends Controller
{
    protected MessageProviderFactory $providerFactory;

    public function __construct(MessageProviderFactory $providerFactory)
    {
        $this->providerFactory = $providerFactory;
    }

    /**
     * Handle SMS Misr webhook
     */
    public function handle(Request $request): Response
    {
        try {
            $provider = $this->providerFactory->make('smsmisr');

            // Get the raw payload
            $payload = $request->getContent();
            $signature = $request->header('X-SMS-Misr-Signature', '');

            // Verify webhook signature
            if (!$provider->verifyWebhook($payload, $signature)) {
                Log::warning('SMS Misr webhook signature verification failed', [
                    'payload' => $payload,
                    'signature' => $signature,
                ]);

                return response('Unauthorized', 401);
            }

            // Parse the payload
            $data = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('SMS Misr webhook invalid JSON payload', [
                    'payload' => $payload,
                    'error' => json_last_error_msg(),
                ]);

                return response('Bad Request', 400);
            }

            // Process the webhook
            $result = $provider->processWebhook($data);

            // Update message status in database
            $this->updateMessageStatus($result);

            Log::info('SMS Misr webhook processed successfully', [
                'message_id' => $result['message_id'],
                'status' => $result['status'],
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('SMS Misr webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * Update message status in database
     */
    protected function updateMessageStatus(array $webhookData): void
    {
        // TODO: Implement when we have the MessageLog model
        // This will be implemented in Phase 3: Database Schema & Models

        Log::info('Message status update queued', [
            'message_id' => $webhookData['message_id'],
            'status' => $webhookData['status'],
            'provider' => 'smsmisr',
        ]);
    }
}
