<?php

namespace Modules\BkashPG\app\Traits;

use Illuminate\Support\Facades\Cache;
use Modules\BkashPG\app\Models\BkashPGModel;

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
    public function getBkashConfig()
    {
        if (!Cache::has('bkashConfig')) {
            $bkashCredential = Cache::rememberForever('bkashConfig', function () {
                return (object) BkashPGModel::pluck('value', 'key')->toArray();
            });
        } else {
            $bkashCredential = (object) Cache::get('bkashConfig');
        }

        return $bkashCredential;
    }

    /**
     * @param  $url
     * @param  $refresh_token
     * @param  null             $account
     * @return mixed
     */
    protected function getUrlToken($url, $refresh_token = null, $account = null)
    {
        session()->forget('bkash_token');
        session()->forget('bkash_token_type');
        session()->forget('bkash_refresh_token');

        $bkashCredential = $this->getBkashConfig();

        $post_token = [
            'app_key'       => $bkashCredential->bkash_key,
            'app_secret'    => $bkashCredential->bkash_secret,
            'refresh_token' => $refresh_token,
        ];
        $url        = curl_init($this->baseUrl . $url);
        $post_token = json_encode($post_token);

        $username = $bkashCredential->bkash_username;
        $password = $bkashCredential->bkash_password;

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
            session()->put('bkash_token', $response['id_token']);
            session()->put('bkash_token_type', $response['token_type']);
            session()->put('bkash_refresh_token', $response['refresh_token']);
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
        $bkashCredential = $this->getBkashConfig();
        $token           = session()->get('bkash_token');
        $app_key         = $bkashCredential->bkash_key;

        $url    = curl_init($this->baseUrl . $url);
        $header = [
            'Content-Type:application/json',
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
        $bkashCredential = $this->getBkashConfig();

        $post_token = [
            'paymentID' => $paymentID,
        ];
        $url = curl_init($this->baseUrl . $url);

        $posttoken = json_encode($post_token);

        $app_key = $bkashCredential->bkash_key;

        $header = [
            'Content-Type:application/json',
            'Authorization:' . session()->get('bkash_token'),
            'X-APP-Key:' . $app_key,
        ];
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $posttoken);
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
        $bkashCredential = $this->getBkashConfig();
        $url             = curl_init($this->baseUrl . $url);
        $app_key         = $bkashCredential->bkash_key;
        $header          = [
            'Content-Type:application/json',
            'Authorization:' . session()->get('bkash_token'),
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
