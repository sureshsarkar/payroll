<?php

namespace Modules\BkashPG\app\Http\Controllers\Payment;

use Modules\BkashPG\app\Traits\Helpers;

class TBRefund extends TBBaseApi
{
    use Helpers;

    /**
     * @param  $paymentID
     * @param  $trxID
     * @param  $amount
     * @param  $reason
     * @param  $sku
     * @param  $account
     * @return mixed
     */
    public function refund($paymentID, $trxID, $amount, $reason = 'refined amount', $sku = 'abc', $account = 1)
    {
        if ($account == 1) {
            $account = null;
        } else {
            $account = "_$account";
        }

        $post_token = [
            'paymentID' => $paymentID,
            'amount'    => $amount,
            'trxID'     => $trxID,
            'reason'    => $reason,
            'sku'       => $sku,
        ];
        $posttoken = json_encode($post_token);
        $this->getToken($account);
        return $this->getUrl3("/checkout/payment/refund", $posttoken, $account);
    }

    /**
     * @param  $paymentID
     * @param  $trxID
     * @param  $account
     * @return mixed
     */
    public function refundStatus($paymentID, $trxID, $account = 1)
    {
        if ($account == 1) {
            $account = null;
        } else {
            $account = "_$account";
        }

        $post_token = [
            'paymentID' => $paymentID,
            'trxID'     => $trxID,
        ];
        $posttoken = json_encode($post_token);
        $this->getToken($account);
        return $this->getUrl3("/checkout/payment/refund", $posttoken, $account);
    }
}
