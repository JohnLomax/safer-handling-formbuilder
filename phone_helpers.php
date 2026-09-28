<?php
declare(strict_types=1);

/**
 * Normalise a phone number before sending to Monday, Forge, Xero, etc.
 * Strips spaces and other separators; keeps digits and an optional leading +.
 */
function normalizePhoneForApi(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }

    $hasPlus = str_starts_with($phone, '+');
    $digits = preg_replace('/\D+/', '', $phone);
    if (! is_string($digits) || $digits === '') {
        return '';
    }

    return $hasPlus ? '+'.$digits : $digits;
}
