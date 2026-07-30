<?php

namespace Modules\BkashPG\app\Enums;

use Illuminate\Support\Str;

enum BkashEnum {
    public static function getBkashSupportedCurrencies(): array
    {
        return [
            'BDT',
        ];

    }

    /**
     * @param $code
     */
    public static function isBkashSupportedCurrencies($code): bool
    {
        return in_array(Str::upper($code), self::getBkashSupportedCurrencies());
    }
}
