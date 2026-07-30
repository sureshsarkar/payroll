<?php

namespace Modules\BkashPG\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Modules\BkashPG\app\Http\Controllers\Payment\TBPayment;

class BkashTokenizePaymentController extends Controller {
    /**
     * @var mixed
     */
    public $tbPayment;

    public function __construct() {
        $this->tbPayment = new TBPayment();
    }

    /**
     * @param $id
     * @param $type
     */
    public function onCreate(Request $request) {

        $currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        $after_success_url = route('payment-success');
        $after_failed_url = route('payment-failed');

        try {
            $inv = uniqid();
            $request['intent'] = 'sale';
            $request['mode'] = '0011'; //0011 for checkout
            $request['payerReference'] = $inv;
            $request['currency'] = str($currency)->upper();
            $request['amount'] = $paid_amount;
            $request['merchantInvoiceNumber'] = $inv;
            $request['callbackURL'] = route('pay.bkash.success');

            $request_data_json = json_encode($request->all());

            $response = $this->tbPayment->cPayment($request_data_json);

        } catch (Exception $ex) {
            info($ex->getMessage());
            return redirect($after_failed_url);
        }

        if (isset($response['bkashURL'])) {
            Session::put('after_success_url', $after_success_url);
            Session::put('after_failed_url', $after_failed_url);
            return redirect()->away($response['bkashURL']);
        } else {
            return redirect($after_failed_url);
        }
    }

    /**
     * @param Request $request
     */
    public function onSuccess(Request $request) {
        $after_failed_url = Session::get('after_failed_url');

        if ($request->status == 'success') {
            $response = $this->tbPayment->executePayment($request->paymentID);

            if (!$response) {
                $response = $this->tbPayment->queryPayment($request->paymentID);
            }

            if (isset($response['statusCode']) && $response['statusCode'] == "0000" && $response['transactionStatus'] == "Completed") {
                $paymentDetails = [
                    'payments_captures_id' => $this->checkArrayIsset($response['paymentID']),
                    'amount'               => $this->checkArrayIsset($response['amount']),
                    'currency'             => $this->checkArrayIsset($response['currency']),
                    'paid'                 => $this->checkArrayIsset($response['amount']),
                    'status'               => $this->checkArrayIsset($response['transactionStatus']),
                ];

                session()->put('after_success_transaction', $this->checkArrayIsset($response['trxID']));
                session()->put('payment_details', $paymentDetails);

                $after_success_url = Session::get('after_success_url');
                return redirect($after_success_url);
            }
            return redirect($after_failed_url);
        } else {
            return redirect($after_failed_url);
        }
    }
    private function checkArrayIsset($value) {
        return isset($value) ? $value : null;
    }
}
