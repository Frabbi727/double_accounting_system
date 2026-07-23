<?php

namespace App\Support;

/**
 * Money formatting for the shop (NFR-11): the ৳ sign, Bangladeshi digit
 * grouping (lakh/crore, e.g. ১,২০,০০০) and Bengali numerals when the active
 * locale is Bangla.
 */
class Money
{
    private const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    /** Format an amount as e.g. "৳ ১,২০,০০০.০০" (bn) or "৳ 1,20,000.00" (en). */
    public static function taka(float|int|string $amount, int $decimals = 2): string
    {
        $sign = (float) $amount < 0 ? '-' : '';
        $formatted = self::indianGroup(abs((float) $amount), $decimals);

        $currency = config('shop.currency', '৳');

        if (app()->getLocale() === 'bn') {
            $formatted = self::toBengaliDigits($formatted);
        }

        return $sign.$currency.' '.$formatted;
    }

    /** Group digits in the South-Asian style: last 3, then pairs (1,20,000). */
    private static function indianGroup(float $amount, int $decimals): string
    {
        $parts = explode('.', number_format($amount, $decimals, '.', ''));
        $integer = $parts[0];
        $fraction = $parts[1] ?? '';

        $last3 = substr($integer, -3);
        $rest = substr($integer, 0, -3);

        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $grouped = $rest.','.$last3;
        } else {
            $grouped = $last3;
        }

        return $decimals > 0 ? $grouped.'.'.$fraction : $grouped;
    }

    private static function toBengaliDigits(string $value): string
    {
        return strtr($value, [
            '0' => self::BN_DIGITS[0], '1' => self::BN_DIGITS[1], '2' => self::BN_DIGITS[2],
            '3' => self::BN_DIGITS[3], '4' => self::BN_DIGITS[4], '5' => self::BN_DIGITS[5],
            '6' => self::BN_DIGITS[6], '7' => self::BN_DIGITS[7], '8' => self::BN_DIGITS[8],
            '9' => self::BN_DIGITS[9],
        ]);
    }
}
