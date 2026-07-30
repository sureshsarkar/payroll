<?php

namespace Modules\BasicPayment\app\Enums;

use Illuminate\Support\Str;
use Modules\BasicPayment\app\Enums\BasicPaymentSupportedCurrencyListEnum;

enum PaymentGatewaySupportedCurrencyListEnum {
    public static function getRazorpaySupportedCurrencies(): array
    {
        return ['AED', 'ALL', 'AMD', 'ARS', 'AUD', 'AWG', 'BBD', 'BDT', 'BMD', 'BND', 'BOB', 'BSD', 'BWP', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC', 'CUP', 'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'GBP', 'GHS', 'GIP', 'GMD', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'KES', 'KGS', 'KHR', 'KYD', 'KZT', 'LAK', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MKD', 'MMK', 'MNT', 'MOP', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PEN', 'PGK', 'PHP', 'PKR', 'QAR', 'RUB', 'SAR', 'SCR', 'SEK', 'SGD', 'SLL', 'SOS', 'SSP', 'SVC', 'SZL', 'THB', 'TTD', 'TZS', 'USD', 'UYU', 'UZS', 'YER', 'ZAR', 'TRY'];
    }

    /**
     * @param $code
     */
    public static function isRazorpaySupportedCurrencies($code): bool
    {
        return in_array(Str::upper($code), self::getRazorpaySupportedCurrencies());
    }

    public static function getMollieSupportedCurrencies(): array
    {
        $mollie  = ['AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'ISK', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR'];
        $paypale = BasicPaymentSupportedCurrencyListEnum::getPaypalSupportedCurrencies();

        return array_unique(array_merge($mollie, $paypale));
    }

    /**
     * @param $code
     */
    public static function isMollieSupportedCurrencies($code): bool
    {
        return in_array(Str::upper($code), self::getMollieSupportedCurrencies());
    }

    public static function getInstamojoSupportedCurrencies(): array
    {
        return ['INR'];
    }

    /**
     * @param $code
     */
    public static function isInstamojoSupportedCurrencies($code): bool
    {
        return in_array(Str::upper($code), self::getInstamojoSupportedCurrencies());
    }

    public static function getPaystackSupportedCurrencies()
    {
        return ['GHS', 'NGN', 'ZAR', 'KES'];
    }

    /**
     * @param $code
     */
    public static function isPaystackSupportedCurrencies($code): bool
    {
        return in_array(Str::upper($code), self::getPaystackSupportedCurrencies());
    }

    public static function getFlutterwaveSupportedCurrencies()
    {
        // return ['NGN' => 'NG'];
        // currency code => country code
        return [
            'AED' => 'AE', 'ANG' => 'AN', 'ARS' => 'AR', 'AUD' => 'AU', 'BHD' => 'BH', 'BRL' => 'BR',
            'CAD' => 'CA', 'CHF' => 'CH', 'CNY' => 'CN', 'CZK' => 'CZ', 'DKK' => 'DK', 'EGP' => 'EG',
            'EUR' => 'EU', 'GBP' => 'GB', 'HKD' => 'HK', 'INR' => 'IN', 'IQD' => 'IQ', 'IRR' => 'IR',
            'ISK' => 'IS', 'JMD' => 'JM', 'JPY' => 'JP', 'KES' => 'KE', 'KRW' => 'KR', 'KWD' => 'KW',
            'LKR' => 'LK', 'LYD' => 'LY', 'MAD' => 'MA', 'MUR' => 'MU', 'MXN' => 'MX', 'MYR' => 'MY',
            'NGN' => 'NG', 'NOK' => 'NO', 'NZD' => 'NZ', 'OMR' => 'OM', 'PKR' => 'PK', 'PLN' => 'PL',
            'QAR' => 'QA', 'RON' => 'RO', 'RSD' => 'RS', 'RUB' => 'RU', 'SAR' => 'SA', 'SEK' => 'SE',
            'SGD' => 'SG', 'THB' => 'TH', 'TRY' => 'TR', 'USD' => 'US', 'XAF' => 'CF', 'XOF' => 'CI',
            'XPF' => 'PF', 'ZAR' => 'ZA',
        ];
    }

    /**
     * @param $code
     */
    public static function isFlutterwaveSupportedCurrencies($code): bool
    {
        return array_key_exists(Str::upper($code), self::getFlutterwaveSupportedCurrencies());
    }

    /**
     * @param $currencyCode
     */
    public static function getFlutterwaveCountryCodeByCurrency($currencyCode): ?string
    {
        return self::isFlutterwaveSupportedCurrencies($currencyCode) ? self::getFlutterwaveSupportedCurrencies()[$currencyCode] : null;
    }
}
