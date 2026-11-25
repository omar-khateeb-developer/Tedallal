<?php

namespace App\Support;

final class PhoneNormalizer
{
    private const BLOCKED_SEQUENTIAL_PHONES = [
        '0912345678', '0923456789', '0987654321', '0998765432',
        '0901234567', '0900000000', '0911111111', '0922222222',
        '0933333333', '0944444444', '0955555555', '0966666666',
        '0977777777', '0988888888', '0999999999',
    ];

    /**
     * Convert Arabic-Indic and Eastern Arabic-Indic digits to ASCII digits.
     */
    public static function toAsciiDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /**
     * Strip spaces and formatting symbols while preserving optional leading '+'.
     */
    public static function clean(string $rawPhone): string
    {
        $ascii = self::toAsciiDigits(trim($rawPhone));
        $hasPlus = str_starts_with($ascii, '+');
        $digits = preg_replace('/[^0-9]/', '', $ascii) ?? '';

        return ($hasPlus && $digits !== '') ? ('+' . $digits) : $digits;
    }

    /**
     * Normalize any user-entered phone into canonical international WhatsApp digits (without '+').
     * Examples:
     *   '٠٩٦٧١٢٣٥٦٩'   -> '963967123569'
     *   '+963 0967...' -> '963967...'
     *   '00963967...'  -> '963967...'
     *   '967123569'    -> '963967123569'
     */
    public static function toWhatsAppDigits(string $rawPhone): string
    {
        $digits = preg_replace('/[^0-9]/', '', self::toAsciiDigits($rawPhone)) ?? '';
        if ($digits === '') {
            return '';
        }

        // Convert international 00 prefix (e.g. 009639... -> 9639...)
        while (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Fix accidental trunk zero kept after major Arab country codes (+963 09... -> 9639...)
        foreach (['963', '966', '971', '962', '964', '965', '974', '973', '968', '961', '20'] as $cc) {
            if (str_starts_with($digits, $cc . '0') && strlen($digits) >= strlen($cc) + 8) {
                $digits = $cc . substr($digits, strlen($cc) + 1);
                break;
            }
        }

        // Syrian local mobile formats: 09XXXXXXXX (10 digits) or 9XXXXXXXX (9 digits)
        if (str_starts_with($digits, '09') && strlen($digits) === 10) {
            return '963' . substr($digits, 1);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 9) {
            return '963' . $digits;
        }

        return $digits;
    }

    /**
     * Format clean display/storage string: Syrian numbers as 09XXXXXXXX, others as +<intl>.
     */
    public static function toStorageFormat(string $rawPhone): string
    {
        $wa = self::toWhatsAppDigits($rawPhone);
        if ($wa === '') {
            return '';
        }

        if (str_starts_with($wa, '9639') && strlen($wa) === 12) {
            return '0' . substr($wa, 3);
        }

        return '+' . $wa;
    }

    /**
     * Validate that a phone number is plausible for WhatsApp OTP/CRM delivery.
     */
    public static function isValidWhatsAppCandidate(string $rawPhone): bool
    {
        $wa = self::toWhatsAppDigits($rawPhone);
        $len = strlen($wa);

        if ($len < 9 || $len > 15) {
            return false;
        }

        // Reject Syrian prefix with invalid length or operator zero
        if (str_starts_with($wa, '9639')) {
            if ($len !== 12 || $wa[4] === '0') {
                return false;
            }
            $local = '0' . substr($wa, 3);
            if (preg_match('/^09(\d)\1{7}$/', $local) || in_array($local, self::BLOCKED_SEQUENTIAL_PHONES, true)) {
                return false;
            }
        }

        // Reject all identical digits (e.g. 9999999999)
        if (preg_match('/^(\d)\1{7,}$/', $wa)) {
            return false;
        }

        return true;
    }
}
