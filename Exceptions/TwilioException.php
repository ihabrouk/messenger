<?php

namespace Ihabrouk\Messenger\Exceptions;

class TwilioException extends MessengerException
{
    public static function fromTwilioError(array $twilioError, array $context = []): self
    {
        $code = $twilioError['code'] ?? '';
        $message = $twilioError['message'] ?? 'Unknown Twilio error';

        return new self($message, (string) $code, array_merge($context, $twilioError));
    }

    public function isTemporaryFailure(): bool
    {
        // Twilio error codes that indicate temporary failures
        $temporaryErrorCodes = [
            '30001', // Queue overflow
            '30002', // Account suspended
            '30003', // Unreachable destination handset
            '30004', // Message blocked
            '30005', // Unknown destination handset
            '30006', // Landline or unreachable carrier
            '30007', // Carrier violation
            '30008', // Unknown error
            '30009', // Missing segment
            '30010', // Message price exceeds max price
        ];

        return in_array($this->getErrorCode(), $temporaryErrorCodes);
    }

    public function isAuthenticationError(): bool
    {
        $authErrorCodes = ['20003', '20404'];
        return in_array($this->getErrorCode(), $authErrorCodes);
    }

    public function isInsufficientFunds(): bool
    {
        return $this->getErrorCode() === '20429';
    }

    public function isInvalidPhoneNumber(): bool
    {
        $invalidPhoneCodes = ['21211', '21212', '21213', '21214', '21215', '21216', '21217'];
        return in_array($this->getErrorCode(), $invalidPhoneCodes);
    }
}
