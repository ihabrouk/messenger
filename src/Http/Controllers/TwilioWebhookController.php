<?php

namespace Ihabrouk\Messenger\Http\Controllers;

use Exception;
use App\Http\Controllers\Controller;
use Ihabrouk\Messenger\Services\MessageProviderFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    protected MessageProviderFactory $providerFactory;

    public function __construct(MessageProviderFactory $providerFactory)
    {
        $this->providerFactory = $providerFactory;
    }

    /**
     * Handle Twilio webhook
     */
    public function handle(Request $request): Response
    {
        try {
            $provider = $this->providerFactory->make('twilio');

            // Get the raw payload and signature
            $payload = $request->getContent();
            $signature = $request->header('X-Twilio-Signature', '');

            // Verify webhook signature
            if (!$provider->verifyWebhook($payload, $signature)) {
                Log::warning('Twilio webhook signature verification failed', [
                    'url' => $request->url(),
                    'signature' => $signature,
                ]);

                return response('Unauthorized', 401);
            }

            // Get the form data
            $data = $request->all();

            // Process the webhook
            $result = $provider->processWebhook($data);

            // Update message status in database
            $this->updateMessageStatus($result);

            Log::info('Twilio webhook processed successfully', [
                'message_sid' => $result['message_id'],
                'status' => $result['status'],
                'from' => $data['From'] ?? null,
                'to' => $data['To'] ?? null,
            ]);

            // Twilio expects TwiML response for some webhooks
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');

        } catch (Exception $e) {
            Log::error('Twilio webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 500)
                ->header('Content-Type', 'text/xml');
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
            'provider' => 'twilio',
            'error_code' => $webhookData['error_code'] ?? null,
            'error_message' => $webhookData['error_message'] ?? null,
        ]);
    }
}
