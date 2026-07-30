<?php

namespace Modules\CryptoPayment\app\Enums;

use Illuminate\Support\Str;

enum CryptoEnum
{
    public static function getCryptoSupportedCurrencies(): array
    {
        return [
            'BTC',
            'ETH',
            'USD',
            'GBP',
            'EUR',
            'BNB',
            'XRP',
            'USDC',
            'DOGE',
            'ADA',
        ];
    }

    /**
     * @param $code
     */
    public static function isCryptoSupportedCurrencies($code): bool
    {
        return in_array(Str::upper($code), self::getCryptoSupportedCurrencies());
    }
}
