<?php

namespace Modules\MercadoPagoPG\app\Enums;

use Illuminate\Support\Str;

enum MercadoPagoEnum
{
    public static function getMercadoPagoSupportedCurrencies(): array
    {
        return [
            'ARS',
            'BRL',
        ];
    }

    /**
     * @param $code
     */
    public static function isMercadoPagoSupportedCurrencies($code): bool
    {
        return in_array(Str::upper($code), self::getMercadoPagoSupportedCurrencies());
    }
}
