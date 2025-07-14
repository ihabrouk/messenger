<?php

namespace App\Messenger\Enums;

enum MessageLanguage: string
{
    case ENGLISH = 'en';
    case ARABIC = 'ar';
    case UNICODE = 'unicode';

    public function label(): string
    {
        return match ($this) {
            self::ENGLISH => 'English',
            self::ARABIC => 'Arabic',
            self::UNICODE => 'Unicode',
        };
    }

    public function smsMisrCode(): int
    {
        return match ($this) {
            self::ENGLISH => 1,
            self::ARABIC => 2,
            self::UNICODE => 3,
        };
    }

    public function maxCharacters(): int
    {
        return match ($this) {
            self::ENGLISH => 160,
            self::ARABIC => 70,
            self::UNICODE => 280,
        };
    }

    public function maxCharactersLong(): int
    {
        return match ($this) {
            self::ENGLISH => 153,
            self::ARABIC => 67,
            self::UNICODE => 268,
        };
    }

    public static function detectFromText(string $text): self
    {
        // Check for Arabic characters
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return self::ARABIC;
        }

        // Check for Unicode characters beyond basic ASCII
        if (preg_match('/[^\x00-\x7F]/', $text)) {
            return self::UNICODE;
        }

        return self::ENGLISH;
    }
}
