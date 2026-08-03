<?php

namespace Modules\Payroll\app\Support;

/**
 * Converts a rupee amount to words using the Indian numbering system
 * (thousand / lakh / crore) — e.g. 55500 → "Rupees Fifty-Five Thousand Five
 * Hundred Only". Paise, when present, are rendered as "and NN Paise".
 *
 * Kept dependency-free (no ext-intl) so it works on every deployment.
 */
class AmountToWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function rupees(float $amount): string
    {
        $amount = round($amount, 2);
        $rupees = (int) floor($amount);
        $paise  = (int) round(($amount - $rupees) * 100);

        $words = 'Rupees '.self::convert($rupees);
        if ($paise > 0) {
            $words .= ' and '.self::convert($paise).' Paise';
        }

        return $words.' Only';
    }

    /** Convert a non-negative integer to Indian-system words. */
    public static function convert(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $parts = [];
        $units = [
            10000000 => 'Crore',
            100000   => 'Lakh',
            1000     => 'Thousand',
            100      => 'Hundred',
        ];

        foreach ($units as $value => $label) {
            if ($n >= $value) {
                $count = intdiv($n, $value);
                // Crore can itself run into thousands/lakhs, so recurse on it.
                $parts[] = ($value === 10000000 ? self::convert($count) : self::twoDigits($count)).' '.$label;
                $n %= $value;
            }
        }

        if ($n > 0) {
            $parts[] = self::twoDigits($n);
        }

        return trim(implode(' ', $parts));
    }

    /** Words for a number 0–99 (hyphenated tens, e.g. "Fifty-Five"). */
    private static function twoDigits(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        $tens = self::TENS[intdiv($n, 10)];
        $one  = $n % 10;

        return $one ? $tens.'-'.self::ONES[$one] : $tens;
    }
}
