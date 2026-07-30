<?php

namespace Modules\CryptoPayment\app\Traits;

use Illuminate\Support\Facades\Cache;
use Modules\CryptoPayment\app\Models\CryptoPG;

trait Helpers
{
    /**
     * @return string|null
     */
    public function getIp()
    {
        return request()->ip();
    }

    /**
     * @return mixed
     */
    public function getCryptoConfig()
    {
        if (!Cache::has('cryptoConfig')) {
            $cryptoCredential = Cache::rememberForever('cryptoConfig', function () {
                return (object) CryptoPG::pluck('value', 'key')->toArray();
            });
        } else {
            $cryptoCredential = (object) Cache::get('cryptoConfig');
        }

        return $cryptoCredential;
    }

    /**
     * @param  $url
     * @param  $refresh_token
     * @param  null             $account
     * @return mixed
     */
    protected function getUrlToken($url, $refresh_token = null, $account = null)
    {
        session()->forget('crypto_token');
        session()->forget('crypto_token_type');
        session()->forget('crypto_refresh_token');

        $cryptoCredential = $this->getCryptoConfig();

        $post_token = [
            'app_key'       => $cryptoCredential->crypto_key,
            'app_secret'    => $cryptoCredential->crypto_secret,
            'refresh_token' => $refresh_token,
        ];
        $url        = curl_init($this->baseUrl . $url);
        $post_token = json_encode($post_token);

        $username = $cryptoCredential->crypto_username;
        $password = $cryptoCredential->crypto_password;

        $header = [
            'Content-Type:application/json',
            "password:$password",
            "username:$username",
        ];
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $post_token);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $resultdata = curl_exec($url);
        curl_close($url);

        $response = json_decode($resultdata, true);
        if (array_key_exists('msg', $response)) {
            return $response;
        }
        if (isset($response['id_token']) && isset($response['token_type']) && isset($response['refresh_token'])) {
            session()->put('crypto_token', $response['id_token']);
            session()->put('crypto_token_type', $response['token_type']);
            session()->put('crypto_refresh_token', $response['refresh_token']);
        }
        return $response;
    }

    /**
     * @param $url
     * @param $method
     * @param $data
     * @param null      $account
     */
    protected function getUrl($url, $method, $data = null, $account = null)
    {
        $cryptoCredential = $this->getCryptoConfig();
        $token           = session()->get('crypto_token');
        $app_key         = $cryptoCredential->crypto_key;

        $url    = curl_init($this->baseUrl . $url);
        $header = [
            'Content-Type:application/x-www-form-urlencoded',
            "authorization: $token",
            "x-app-key: $app_key",
        ];

        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        if ($data) {
            curl_setopt($url, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);
        curl_close($url);
        return json_decode($resultdata, true);
    }

    /**
     * @param $paymentID
     * @param $url
     * @param $account
     */
    protected function getUrl2($paymentID, $url, $account = null)
    {
        $cryptoCredential = $this->getCryptoConfig();

        $post_token = [
            'paymentID' => $paymentID,
        ];
        $url = curl_init($this->baseUrl . $url);

        $posttoken = json_encode($post_token);


        $header = [
            'Content-Type:application/json',
            'Authorization:' . session()->get('crypto_token'),
        ];
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);
        curl_close($url);

        return json_decode($resultdata, true);
    }

    /**
     * @param $url
     * @param $data
     * @param $account
     */
    protected function getUrl3($url, $data, $account = null)
    {
        $cryptoCredential = $this->getCryptoConfig();
        $url             = curl_init($this->baseUrl . $url);
        $app_key         = $cryptoCredential->crypto_key;
        $header          = [
            'Content-Type:application/json',
            'Authorization:' . session()->get('crypto_token'),
            'x-app-key:' . $app_key,
        ];
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        if ($data) {
            curl_setopt($url, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);
        curl_close($url);

        return json_decode($resultdata, true);
    }

    /**
     * @param  $account
     * @return mixed
     */
    protected function getToken($account = null)
    {
        return $this->getUrlToken('/checkout/token/grant', null, $account);
    }
}
