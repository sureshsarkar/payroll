<?php

namespace Modules\MercadoPagoPG\app\Services;

use Illuminate\Support\Facades\Cache;
use Modules\BasicPayment\app\Services\PaymentMethodService;
use Modules\MercadoPagoPG\app\Enums\MercadoPagoEnum;
use Modules\MercadoPagoPG\app\Models\MercadoPagoPG;
use Modules\MercadoPagoPG\app\Models\MercadoPagoPGModel;

class MercadoPagoPaymentGatewayService extends PaymentMethodService
{
    const MercadoPago = 'Mercado Pago';
    private $paymentSetting;
    private $activeStatus;

    /**
     * @var mixed
     */
    protected $previousService;

    /**
     * @param PaymentMethodService $previousService
     */
    public function __construct(?PaymentMethodService $previousService = null)
    {
        if (is_null($previousService)) {
            $previousService = PaymentMethodService::class;
        }

        $this->previousService = $previousService;
        $this->paymentSetting = $this->getGatewayDetails(self::MercadoPago);
        $this->activeStatus = config('basicpayment.default_status.active_text');

        self::extendSupportedPayments([
            self::MercadoPago,
        ]);
        self::extendMultiCurrencySupported([
            self::MercadoPago,
        ]);

        self::additionalActiveGatewaysList([
            self::MercadoPago       => [
                'name'   => 'Mercado Pago',
                'logo'   => asset($this->paymentSetting->image ?? 'uploads/payment/mercado-pago.png'),
                'status' => $this->paymentSetting->status == $this->activeStatus,
            ],
        ]);
    }

    /**
     * @param string $gatewayName
     */
    public function getPaymentName(string $gatewayName): ?string
    {
        return match ($gatewayName) {
            self::MercadoPago => 'MercadoPago',
            default => $this->previousService->getPaymentName($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getGatewayDetails(string $gatewayName): ?object
    {
        if (!Cache::has('mercadopagoConfig')) {
            $mercadopagoConfig = Cache::rememberForever('mercadopagoConfig', function () {
                return (object) MercadoPagoPG::pluck('value', 'key')->toArray();
            });
        } else {
            $mercadopagoConfig = Cache::get('mercadopagoConfig');
        }

        return match ($gatewayName) {
            self::MercadoPago => (object) [
                'mercadopago_sandbox'    => $mercadopagoConfig->mercadopago_sandbox == 1 ? true : false,
                'public_key'    => $mercadopagoConfig->public_key,
                'access_token' => $mercadopagoConfig->access_token,
                'status'           => $mercadopagoConfig->mercadopago_status,
                'charge'           => $mercadopagoConfig->mercadopago_charge,
                'image'            => $mercadopagoConfig->mercadopago_image,
            ],
            default => $this->previousService->getGatewayDetails($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function isActive(string $gatewayName): bool
    {
        $gatewayDetails = $this->getGatewayDetails($gatewayName);

        return match ($gatewayName) {
            self::MercadoPago => $gatewayDetails->status == $this->activeStatus,
            default => $this->previousService->isActive($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getIcon(string $gatewayName): string
    {
        return match ($gatewayName) {
            self::MercadoPago => 'fa-cc-MercadoPago',
            default => $this->previousService->getIcon($gatewayName),
        };
    }

    /**
     * @param $gatewayName
     */
    public function getLogo($gatewayName): ?string
    {
        $gatewayDetails = $this->getGatewayDetails(self::MercadoPago);

        return match ($gatewayName) {
            self::MercadoPago => $gatewayDetails->image ? asset($gatewayDetails->image) : asset('uploads/payment/mercado-pago.png'),
            default => $this->previousService->getLogo($gatewayName),
        };
    }

    /**
     * @param $gatewayName
     * @param $code
     */
    public function isCurrencySupported($gatewayName, $code = null): bool
    {
        if (is_null($code)) {
            $code = getSessionCurrency();
        }

        return match ($gatewayName) {
            self::MercadoPago => MercadoPagoEnum::isMercadoPagoSupportedCurrencies($code),
            default => $this->previousService->isCurrencySupported($gatewayName, $code),
        };
    }

    /**
     * @param $gatewayName
     */
    public function getSupportedCurrencies($gatewayName): array
    {
        return match ($gatewayName) {
            self::MercadoPago => MercadoPagoEnum::getMercadoPagoSupportedCurrencies(),
            default => $this->previousService->getSupportedCurrencies($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getBladeView(string $gatewayName): ?string
    {
        return match ($gatewayName) {
            self::MercadoPago => 'mercadopagopg::payment-button',
            default => $this->previousService->getBladeView($gatewayName),
        };
    }
}
