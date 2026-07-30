<?php

namespace Modules\BkashPG\app\Http\Controllers\Payment;

use Modules\BkashPG\app\Traits\Helpers;

class TBPayment extends TBBaseApi
{
    use Helpers;

    /**
     * @param  $request_data_json
     * @param  $account
     * @return mixed
     */
    public function cPayment($request_data_json, $account = 1)
    {
        if ($account == 1) {
            $account = null;
        } else {
            $account = "_$account";
        }

        $response = $this->getToken($account);

        if (isset($response['id_token']) && $response['id_token']) {
            return $this->getUrl('/checkout/create', 'POST', $request_data_json, $account);
        }
        return $response;
    }
    /**
     * @param  $paymentID
     * @param  $account
     * @return mixed
     */
    public function executePayment($paymentID, $account = 1)
    {
        if ($account == 1) {
            $account = null;
        } else {
            $account = "_$account";
        }

        $token = session()->get('bkash_token');
        if (!$token) {
            $this->getToken($account);
        }

        return $this->getUrl2($paymentID, '/checkout/execute', $account);
    }
    /**
     * @param  $paymentID
     * @param  $account
     * @return mixed
     */
    public function queryPayment($paymentID, $account = 1)
    {
        if ($account == 1) {
            $account = null;
        } else {
            $account = "_$account";
        }

        $token = session()->get('bkash_token');
        if (!$token) {
            $this->getToken($account);
        }

        return $this->getUrl2($paymentID, '/checkout/payment/status', $account);
    }
    /**
     * @param  $refresh_token
     * @param  $account
     * @return mixed
     */
    public function refreshToken($refresh_token, $account = 1)
    {
        if ($account == 1) {
            $account = null;
        } else {
            $account = "_$account";
        }

        return $this->getUrlToken("/checkout/token/refresh", $refresh_token, $account);
    }
    /**
     * @param  $trxID
     * @param  $account
     * @return mixed
     */
    public function searchTransaction($trxID, $account = 1)
    {
        if ($account == 1) {
            $account = null;
        } else {
            $account = "_$account";
        }

        $post_token = [
            'trxID' => $trxID,
        ];
        $posttoken = json_encode($post_token);
        $this->getToken($account);
        return $this->getUrl3("/checkout/general/searchTransaction", $posttoken, $account);
    }
    /**
     * @param $message
     * @param $transId
     */
    public function success($message, $transId)
    {
        return view('bkashT::success', compact('message', 'transId'));
    }
    /**
     * @param $message
     * @param $transId
     */
    public function cancel($message, $transId = null)
    {
        return view('bkashT::failed', compact('message', 'transId'));
    }
    /**
     * @param $message
     * @param $transId
     */
    public function failure($message, $transId = null)
    {
        return view('bkashT::failed', compact('message', 'transId'));
    }

}
