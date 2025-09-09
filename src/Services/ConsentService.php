<?php

namespace Ihabrouk\Messenger\Services;

use Exception;
use InvalidArgumentException;
use Ihabrouk\Messenger\Models\Consent;
use Ihabrouk\Messenger\Models\Message;
use Ihabrouk\Messenger\Enums\ConsentStatus;
use Ihabrouk\Messenger\Enums\ConsentType;
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
     * Process opt-in for a phone number
     */
    public function processOptIn(string $phoneNumber, string $type = 'all', string $channel = 'sms'): Consent
    {
        $this->validatePhoneNumber($phoneNumber);

        $consentType = ConsentType::tryFrom($type) ?? ConsentType::ALL;

        return $this->setConsent(
            $phoneNumber,
            $channel,
            ConsentStatus::PENDING,
            $consentType,
            'opt_in_request'
        );
    }

    /**
     * Process opt-out for a phone number
     */
    public function processOptOut(string $phoneNumber, string $reason = null, string $channel = 'sms'): bool
    {
        $consent = $this->getConsent($phoneNumber, $channel);

        if (!$consent) {
            return false;
        }

        $this->setConsent(
            $phoneNumber,
            $channel,
            ConsentStatus::REVOKED,
            $consent->type,
            'opt_out_request',
            null,
            $reason
        );

        return true;
    }

    /**
     * Check if a phone number has consent
     */
    public function hasConsent(string $phoneNumber, string $type = null, string $channel = 'sms'): bool
    {
        $cacheKey = "messenger.consent.{$phoneNumber}." . ($type ?: 'all');

        return Cache::remember($cacheKey, 300, function () use ($phoneNumber, $type, $channel) {
            $query = Consent::where('recipient_phone', $phoneNumber)
                          ->where('channel', $channel)
                          ->whereIn('status', [ConsentStatus::GRANTED, ConsentStatus::OPTED_IN]);

            // Check for expiration based on retention_days config
            $retentionDays = config('messenger.consent.retention_days', 2555);
            $query->where('granted_at', '>', now()->subDays($retentionDays));

            if ($type) {
                $consentType = ConsentType::tryFrom($type);
                if (!$consentType) {
                    return false;
                }
                $query->where('type', $consentType);
            }

            $consent = $query->first();

            if (!$consent) {
                return false;
            }

            if ($type && method_exists($consent, 'canReceive')) {
                return $consent->canReceive($consentType);
            }

            return true;
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
            ConsentStatus::GRANTED,
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
            ConsentStatus::REVOKED,
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
        string $channel = 'sms',
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
        $consents = Consent::where('recipient_phone', $phoneNumber)->get();
        $messages = Message::where('to', $phoneNumber)->get([
            'id', 'channel', 'provider', 'status', 'cost', 'created_at', 'delivered_at'
        ]);

        return [
            'phone_number' => $phoneNumber,
            'consents' => $consents->toArray(),
            'message_history' => $messages->toArray(),
            'metadata' => [
                'export_date' => now()->toISOString(),
                'total_consents' => $consents->count(),
                'total_messages' => $messages->count(),
            ],
        ];
    }

    /**
     * Delete all data for a phone number (GDPR right to be forgotten)
     */
    public function deleteUserData(string $phoneNumber): bool
    {
        $deletedConsents = Consent::where('recipient_phone', $phoneNumber)->delete();
        $deletedMessages = Message::where('to', $phoneNumber)->delete();

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

        return $deletedConsents > 0 || $deletedMessages > 0;
    }

    /**
     * Update preferences for a consent
     */
    public function updatePreferences(
        string $phoneNumber,
        array $preferences,
        string $channel = 'sms'
    ): bool {
        $consent = $this->getConsent($phoneNumber, $channel);

        if (!$consent) {
            return false;
        }

        $consent->updatePreferences($preferences);
        $this->clearConsentCache($phoneNumber, $channel);

        return true;
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
     * Verify consent token
     */
    public function verifyConsent(string $token): bool
    {
        $consent = Consent::where('verification_token', $token)
            ->where('status', ConsentStatus::PENDING)
            ->first();

        if (!$consent) {
            return false;
        }

        $consent->update([
            'status' => ConsentStatus::GRANTED,
            'granted_at' => now(),
            'verification_token' => null,
        ]);

        $this->clearConsentCache($consent->recipient_phone, $consent->channel ?? 'sms');

        return true;
    }

    /**
     * Bulk opt-in for multiple phone numbers
     */
    public function bulkOptIn(array $phoneNumbers, string $type, string $channel = 'sms'): array
    {
        $results = [];
        $consentType = ConsentType::from($type);

        foreach ($phoneNumbers as $phone) {
            try {
                $consent = $this->optIn($phone, $channel, $consentType, 'bulk_operation');
                $results[] = ['phone' => $phone, 'status' => 'success', 'consent_id' => $consent->id];
            } catch (Exception $e) {
                $results[] = ['phone' => $phone, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Anonymize user data
     */
    public function anonymizeUserData(string $phoneNumber): bool
    {
        $consents = Consent::where('recipient_phone', $phoneNumber)->get();

        foreach ($consents as $consent) {
            $consent->update([
                'recipient_phone' => 'ANON_' . substr(hash('sha256', $phoneNumber), 0, 12),
                'anonymized_at' => now(),
                'preferences' => null,
            ]);

            // Clear cache
            $this->clearConsentCache($phoneNumber, $consent->channel ?? 'sms');
        }

        return true;
    }

    /**
     * Clear consent cache
     */
    private function clearConsentCache(string $phoneNumber, string $channel): void
    {
        $cacheKey = "consent:{$phoneNumber}:{$channel}";
        Cache::forget($cacheKey);

        // Also clear the messenger cache key format
        $messengerCacheKey = "messenger.consent.{$phoneNumber}.{$channel}";
        Cache::forget($messengerCacheKey);
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

    /**
     * Validate phone number format
     */
    private function validatePhoneNumber(string $phoneNumber): void
    {
        if (empty(trim($phoneNumber))) {
            throw new InvalidArgumentException('Phone number cannot be empty');
        }

        if (!preg_match('/^\+\d{10,15}$/', $phoneNumber)) {
            throw new InvalidArgumentException('Invalid phone number format. Must start with + and contain 10-15 digits');
        }
    }
}
