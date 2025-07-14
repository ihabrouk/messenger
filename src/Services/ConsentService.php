<?php

namespace App\Messenger\Services;

use App\Messenger\Models\Consent;
use App\Messenger\Models\Message;
use App\Messenger\Enums\ConsentStatus;
use App\Messenger\Enums\ConsentType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ConsentService
{
    public function __construct()
    {
        //
    }

    /**
     * Check if a phone number can receive messages
     */
    public function canReceiveMessages(
        string $phoneNumber, 
        string $channel, 
        ConsentType $messageType = ConsentType::ALL
    ): bool {
        $consent = $this->getConsent($phoneNumber, $channel);

        if (!$consent) {
            // No consent record - check default policy
            return $this->getDefaultConsentPolicy($messageType);
        }

        return $consent->canReceive($messageType);
    }

    /**
     * Get consent record for a phone number and channel
     */
    public function getConsent(string $phoneNumber, string $channel): ?Consent
    {
        $cacheKey = "consent:{$phoneNumber}:{$channel}";
        
        return Cache::remember($cacheKey, 3600, function () use ($phoneNumber, $channel) {
            return Consent::findByPhone($phoneNumber, $channel);
        });
    }

    /**
     * Create or update consent
     */
    public function setConsent(
        string $phoneNumber,
        string $channel,
        ConsentStatus $status,
        ConsentType $type = ConsentType::ALL,
        string $source = null,
        array $preferences = null,
        string $reason = null
    ): Consent {
        $consent = Consent::createOrUpdateConsent(
            $phoneNumber,
            $channel,
            $status,
            $type,
            $source,
            $preferences
        );

        if ($status === ConsentStatus::OPTED_OUT && $reason) {
            $consent->update(['reason' => $reason]);
        }

        // Clear cache
        $this->clearConsentCache($phoneNumber, $channel);

        // Log the consent change
        Log::channel('messenger')->info('Consent updated', [
            'phone_number' => $phoneNumber,
            'channel' => $channel,
            'status' => $status->value,
            'type' => $type->value,
            'source' => $source,
            'reason' => $reason,
        ]);

        return $consent;
    }

    /**
     * Opt in a phone number
     */
    public function optIn(
        string $phoneNumber,
        string $channel,
        ConsentType $type = ConsentType::ALL,
        string $source = null,
        array $preferences = null
    ): Consent {
        return $this->setConsent(
            $phoneNumber,
            $channel,
            ConsentStatus::OPTED_IN,
            $type,
            $source,
            $preferences
        );
    }

    /**
     * Opt out a phone number
     */
    public function optOut(
        string $phoneNumber,
        string $channel,
        string $reason = null,
        string $source = null
    ): Consent {
        return $this->setConsent(
            $phoneNumber,
            $channel,
            ConsentStatus::OPTED_OUT,
            ConsentType::ALL,
            $source,
            null,
            $reason
        );
    }

    /**
     * Bulk opt out phone numbers
     */
    public function bulkOptOut(
        array $phoneNumbers,
        string $channel,
        string $reason = null,
        string $source = null
    ): int {
        $updated = Consent::bulkOptOut($phoneNumbers, $channel, $reason);

        // Clear cache for all affected numbers
        foreach ($phoneNumbers as $phoneNumber) {
            $this->clearConsentCache($phoneNumber, $channel);
        }

        Log::channel('messenger')->info('Bulk opt-out processed', [
            'count' => count($phoneNumbers),
            'channel' => $channel,
            'reason' => $reason,
            'source' => $source,
            'updated' => $updated,
        ]);

        return $updated;
    }

    /**
     * Process SMS reply for opt-out
     */
    public function processSmsReply(string $phoneNumber, string $message): bool
    {
        $optOutKeywords = ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'REMOVE'];
        $optInKeywords = ['START', 'SUBSCRIBE', 'YES', 'JOIN'];

        $message = strtoupper(trim($message));

        if (in_array($message, $optOutKeywords)) {
            $this->optOut(
                $phoneNumber,
                'sms',
                'SMS reply: ' . $message,
                'sms_reply'
            );
            return true;
        }

        if (in_array($message, $optInKeywords)) {
            $this->optIn(
                $phoneNumber,
                'sms',
                ConsentType::ALL,
                'sms_reply'
            );
            return true;
        }

        return false;
    }

    /**
     * Get filtered recipients based on consent
     */
    public function filterRecipients(
        array $phoneNumbers,
        string $channel,
        ConsentType $messageType = ConsentType::ALL
    ): array {
        $allowedRecipients = [];
        $blockedRecipients = [];

        foreach ($phoneNumbers as $phoneNumber) {
            if ($this->canReceiveMessages($phoneNumber, $channel, $messageType)) {
                $allowedRecipients[] = $phoneNumber;
            } else {
                $blockedRecipients[] = $phoneNumber;
            }
        }

        if (!empty($blockedRecipients)) {
            Log::channel('messenger')->info('Recipients filtered due to consent', [
                'channel' => $channel,
                'message_type' => $messageType->value,
                'total_recipients' => count($phoneNumbers),
                'allowed' => count($allowedRecipients),
                'blocked' => count($blockedRecipients),
            ]);
        }

        return [
            'allowed' => $allowedRecipients,
            'blocked' => $blockedRecipients,
        ];
    }

    /**
     * Get consent statistics
     */
    public function getStats(string $channel = null, Carbon $from = null, Carbon $to = null): array
    {
        $cacheKey = "consent_stats:" . md5($channel . ($from?->toString()) . ($to?->toString()));
        
        return Cache::remember($cacheKey, 1800, function () use ($channel, $from, $to) {
            $stats = Consent::getConsentStats($channel);
            $stats['opt_in_rate'] = Consent::getOptInRate($channel, $from, $to);
            
            return $stats;
        });
    }

    /**
     * Anonymize old consent data (GDPR compliance)
     */
    public function anonymizeOldData(int $daysOld = 365): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $anonymized = Consent::where('opted_out_at', '<', $cutoffDate)
            ->whereNotNull('opted_out_at')
            ->update([
                'phone_number' => 'ANONYMIZED',
                'metadata' => null,
                'preferences' => null,
                'reason' => 'Data anonymized due to age',
            ]);

        Log::channel('messenger')->info('Consent data anonymized', [
            'cutoff_date' => $cutoffDate->toDateString(),
            'anonymized_records' => $anonymized,
        ]);

        return $anonymized;
    }

    /**
     * Export consent data for a phone number (GDPR data export)
     */
    public function exportUserData(string $phoneNumber): array
    {
        $consents = Consent::where('phone_number', $phoneNumber)->get();
        $messages = Message::where('recipient_phone', $phoneNumber)->get([
            'id', 'channel', 'provider', 'status', 'cost', 'created_at', 'delivered_at'
        ]);

        return [
            'phone_number' => $phoneNumber,
            'consents' => $consents->toArray(),
            'message_history' => $messages->toArray(),
            'export_date' => now()->toISOString(),
        ];
    }

    /**
     * Delete all data for a phone number (GDPR right to be forgotten)
     */
    public function deleteUserData(string $phoneNumber): array
    {
        $deletedConsents = Consent::where('phone_number', $phoneNumber)->delete();
        $deletedMessages = Message::where('recipient_phone', $phoneNumber)->delete();

        // Clear all caches for this phone number
        $channels = ['sms', 'whatsapp', 'email'];
        foreach ($channels as $channel) {
            $this->clearConsentCache($phoneNumber, $channel);
        }

        Log::channel('messenger')->info('User data deleted', [
            'phone_number' => $phoneNumber,
            'deleted_consents' => $deletedConsents,
            'deleted_messages' => $deletedMessages,
        ]);

        return [
            'deleted_consents' => $deletedConsents,
            'deleted_messages' => $deletedMessages,
        ];
    }

    /**
     * Update preferences for a consent
     */
    public function updatePreferences(
        string $phoneNumber,
        string $channel,
        array $preferences
    ): ?Consent {
        $consent = $this->getConsent($phoneNumber, $channel);
        
        if (!$consent) {
            return null;
        }

        $consent->updatePreferences($preferences);
        $this->clearConsentCache($phoneNumber, $channel);

        return $consent;
    }

    /**
     * Get double opt-in verification URL
     */
    public function generateDoubleOptInUrl(
        string $phoneNumber,
        string $channel,
        ConsentType $type = ConsentType::ALL
    ): string {
        $token = hash('sha256', $phoneNumber . $channel . time() . config('app.key'));
        
        Cache::put("double_optin:{$token}", [
            'phone_number' => $phoneNumber,
            'channel' => $channel,
            'type' => $type,
        ], 3600); // 1 hour expiry

        return url("/messenger/consent/verify/{$token}");
    }

    /**
     * Verify double opt-in token
     */
    public function verifyDoubleOptIn(string $token): ?Consent
    {
        $data = Cache::get("double_optin:{$token}");
        
        if (!$data) {
            return null;
        }

        Cache::forget("double_optin:{$token}");

        return $this->optIn(
            $data['phone_number'],
            $data['channel'],
            ConsentType::from($data['type']),
            'double_optin'
        );
    }

    /**
     * Clear consent cache
     */
    private function clearConsentCache(string $phoneNumber, string $channel): void
    {
        $cacheKey = "consent:{$phoneNumber}:{$channel}";
        Cache::forget($cacheKey);
    }

    /**
     * Get default consent policy when no record exists
     */
    private function getDefaultConsentPolicy(ConsentType $messageType): bool
    {
        // For transactional messages, default to allow
        if ($messageType === ConsentType::TRANSACTIONAL) {
            return config('messenger.consent.allow_transactional_by_default', true);
        }

        // For marketing, default to require explicit opt-in
        return config('messenger.consent.allow_marketing_by_default', false);
    }
}
