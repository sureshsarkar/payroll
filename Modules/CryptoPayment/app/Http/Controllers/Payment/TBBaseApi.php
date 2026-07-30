<?php

namespace Modules\CryptoPayment\app\Http\Controllers\Payment;

use Modules\CryptoPayment\app\Traits\Helpers;

class TBBaseApi
{
    use Helpers;

    /**
     * @var string $baseUrl
     */
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl();
    }

    /**
     * bkash Base Url
     * if sandbox is true it will be sandbox url otherwise it is host url
     */
    private function baseUrl()
    {
        $config = $this->getCryptoConfig();

        if ($config->bkash_sandbox == 1) {
            $this->baseUrl = 'https://api-sandbox.coingate.com/api/v2';
        } else {
            $this->baseUrl = 'https://api.coingate.com/api/v2';
        }
    }

    /**
     * bkash Request Headers
     *
     * @return array
     */
    protected function headers()
    {
        return [
            "Content-Type"     => "application/json",
            'accept' => 'text/plain',
            'content-type' => 'application/x-www-form-urlencoded',
        ];
    }
}
