<?php

namespace Modules\BasicPayment\app\Interfaces;

interface PaymentMethodInterface
{
    /**
     * Get the name of a specific payment gateway.
     *
     * @param  string        $gatewayName
     * @return string|null
     */
    public function getPaymentName(string $gatewayName): ?string;

    /**
     * Get details for a specific payment gateway.
     *
     * @param  string        $gatewayName
     * @return object|null
     */
    public function getGatewayDetails(string $gatewayName): ?object;

    /**
     * Check if a specific payment gateway is active.
     *
     * @param  string $gatewayName
     * @return bool
     */
    public function isActive(string $gatewayName): bool;

    /**
     * Get the icon associated with a specific payment gateway.
     *
     * @param  string   $gatewayName
     * @return string
     */
    public function getIcon(string $gatewayName): string;

    /**
     * Get the logo associated with a specific payment gateway.
     *
     * @param  string        $gatewayName
     * @return string|null
     */
    public function getLogo(string $gatewayName): ?string;

    /**
     * Check if a specific currency is supported by a payment gateway.
     *
     * @param  string      $gatewayName
     * @param  string|null $code
     * @return bool
     */
    public function isCurrencySupported(string $gatewayName, $code = null): bool;

    /**
     * Get the list of supported currencies for a specific payment gateway.
     *
     * @param  string  $gatewayName
     * @return array
     */
    public function getSupportedCurrencies(string $gatewayName): array;

    /**
     * @param string $gatewayName
     */
    public function getBladeView(string $gatewayName): ?string;
}
