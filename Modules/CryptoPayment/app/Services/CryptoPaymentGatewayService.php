<?php

namespace Modules\CryptoPayment\app\Services;

use Illuminate\Support\Facades\Cache;
use Modules\BasicPayment\app\Services\PaymentMethodService;
use Modules\CryptoPayment\app\Enums\CryptoEnum;
use Modules\CryptoPayment\app\Models\CryptoPG;

class CryptoPaymentGatewayService extends PaymentMethodService
{
    const CRYPTO = 'CoinGate';
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
        $this->paymentSetting = $this->getGatewayDetails(self::CRYPTO);
        $this->activeStatus = config('basicpayment.default_status.active_text');

        self::extendSupportedPayments([
            self::CRYPTO,
        ]);
        self::extendMultiCurrencySupported([
            self::CRYPTO,
        ]);

        self::additionalActiveGatewaysList([
            self::CRYPTO       => [
                'name'   => 'Crypto',
                'logo'   => asset($this->paymentSetting->image ?? 'uploads/website-images/coingate.webp'),
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
            self::CRYPTO => 'CoinGate',
            default => $this->previousService->getPaymentName($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getGatewayDetails(string $gatewayName): ?object
    {
        if (!Cache::has('cryptoConfig')) {
            $cryptoData = Cache::rememberForever('cryptoConfig', function () {
                return (object) CryptoPG::pluck('value', 'key')->toArray();
            });
        } else {
            $cryptoData = Cache::get('cryptoConfig');
        }

        return match ($gatewayName) {
            self::CRYPTO => (object) [
                'crypto_sandbox'    => $cryptoData->crypto_sandbox == 1 ? true : false,
                'crypto_token'    => $cryptoData->crypto_token,
                'status'           => $cryptoData->crypto_status,
                'image'            => $cryptoData->crypto_image,
                'charge'           => $cryptoData->crypto_charge,
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
            self::CRYPTO => $gatewayDetails->status == $this->activeStatus,
            default => $this->previousService->isActive($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getIcon(string $gatewayName): string
    {
        return match ($gatewayName) {
            self::CRYPTO => 'fa-cc-crypto',
            default => $this->previousService->getIcon($gatewayName),
        };
    }

    /**
     * @param $gatewayName
     */
    public function getLogo($gatewayName): ?string
    {
        $gatewayDetails = $this->getGatewayDetails(self::CRYPTO);

        return match ($gatewayName) {
            self::CRYPTO => $gatewayDetails->image ? asset($gatewayDetails->image) : asset('uploads/website-images/coingate.webp'),
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
            self::CRYPTO => CryptoEnum::isCryptoSupportedCurrencies($code),
            default => $this->previousService->isCurrencySupported($gatewayName, $code),
        };
    }

    /**
     * @param $gatewayName
     */
    public function getSupportedCurrencies($gatewayName): array
    {
        return match ($gatewayName) {
            self::CRYPTO => CryptoEnum::getCryptoSupportedCurrencies(),
            default => $this->previousService->getSupportedCurrencies($gatewayName),
        };
    }

    /**
     * @param string $gatewayName
     */
    public function getBladeView(string $gatewayName): ?string
    {
        return match ($gatewayName) {
            self::CRYPTO => 'cryptopayment::payment-button',
            default => $this->previousService->getBladeView($gatewayName),
        };
    }
}
