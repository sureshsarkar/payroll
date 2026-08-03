<?php

namespace Tests\Feature\Payroll;

use Modules\Payroll\app\Support\AmountToWords;
use Tests\TestCase;

/**
 * Locks the Indian-numbering amount-in-words used on the Form XI pay slip.
 */
class AmountToWordsTest extends TestCase
{
    /** @dataProvider cases */
    public function test_rupees_in_words(float $amount, string $expected): void
    {
        $this->assertSame($expected, AmountToWords::rupees($amount));
    }

    public static function cases(): array
    {
        return [
            'sample slip'   => [55500, 'Rupees Fifty-Five Thousand Five Hundred Only'],
            'zero'          => [0, 'Rupees Zero Only'],
            'hundreds'      => [101, 'Rupees One Hundred One Only'],
            'exact thousand'=> [1000, 'Rupees One Thousand Only'],
            'lakh'          => [150000, 'Rupees One Lakh Fifty Thousand Only'],
            'crore'         => [10000000, 'Rupees One Crore Only'],
            'with paise'    => [55500.75, 'Rupees Fifty-Five Thousand Five Hundred and Seventy-Five Paise Only'],
        ];
    }
}
