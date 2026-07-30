<?php

namespace Modules\BkashPG\app\Services;

use Illuminate\Support\Facades\Cache;
use Modules\BasicPayment\app\Services\PaymentMethodService;
use Modules\BkashPG\app\Enums\BkashEnum;
use Modules\BkashPG\app\Models\BkashPGModel;

class BkashPaymentGatewayService extends PaymentMethodService
{
    const BKASH = 'bkash';
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
        $this->paymentSetting = $this->getGatewayDetails(self::BKASH);
        $this->activeStatus = config('basicpayment.default_status.active_text');

        self::extendSupportedPayments([
            self::BKASH,
        ]);
        self::extendMultiCurrencySupported([
            self::BKASH,
        ]);

        self::additionalActiveGatewaysList([
            self::BKASH       => [
                'name'   => 'Bkash',
                'logo'   => asset($this->paymentSetting->image ?? 'uploads/payment/bkash.png'),
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
            self::BKASH => 'Bkash',
            default => $this->previousService->getPaymentName($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getGatewayDetails(string $gatewayName): ?object
    {
        if (!Cache::has('bkashConfig')) {
            $bkashData = Cache::rememberForever('bkashConfig', function () {
                return (object) BkashPGModel::pluck('value', 'key')->toArray();
            });
        } else {
            $bkashData = Cache::get('bkashConfig');
        }

        return match ($gatewayName) {
            self::BKASH => (object) [
                'bkash_sandbox'    => $bkashData->bkash_sandbox == 1 ? true : false,
                'bkash_app_key'    => $bkashData->bkash_key,
                'bkash_app_secret' => $bkashData->bkash_secret,
                'bkash_username'   => $bkashData->bkash_username,
                'bkash_password'   => $bkashData->bkash_password,
                'status'           => $bkashData->bkash_status,
                'charge'           => $bkashData->bkash_charge,
                'image'            => $bkashData->bkash_image,
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
            self::BKASH => $gatewayDetails->status == $this->activeStatus,
            default => $this->previousService->isActive($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getIcon(string $gatewayName): string
    {
        return match ($gatewayName) {
            self::BKASH => 'fa-cc-bkash',
            default => $this->previousService->getIcon($gatewayName),
        };
    }

    /**
     * @param $gatewayName
     */
    public function getLogo($gatewayName): ?string
    {
        $gatewayDetails = $this->getGatewayDetails(self::BKASH);

        return match ($gatewayName) {
            self::BKASH => $gatewayDetails->image ? asset($gatewayDetails->image) : asset('uploads/payment/bkash.png'),
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
            self::BKASH => BkashEnum::isBkashSupportedCurrencies($code),
            default => $this->previousService->isCurrencySupported($gatewayName, $code),
        };
    }

    /**
     * @param $gatewayName
     */
    public function getSupportedCurrencies($gatewayName): array
    {
        return match ($gatewayName) {
            self::BKASH => BkashEnum::getBkashSupportedCurrencies(),
            default => $this->previousService->getSupportedCurrencies($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getBladeView(string $gatewayName): ?string
    {
        return match ($gatewayName) {
            self::BKASH => 'bkashpg::payment-button',
            default => $this->previousService->getBladeView($gatewayName),
        };
    }

}
