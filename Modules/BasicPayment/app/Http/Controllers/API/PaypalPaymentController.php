<?php

namespace Modules\BasicPayment\app\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ShoppingCart;
use App\Traits\GetGlobalInformationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalPaymentController extends Controller
{
    use GetGlobalInformationTrait;
    private $paymentService;
    public function __construct() {
        $this->paymentService = app(\Modules\BasicPayment\app\Services\PaymentMethodService::class);
    }

    public function pay_via_paypal() {
        // Coach-specific gateway (2026-06-29) — resolve PayPal credentials from the
        // order's stamped gateway config (coach or platform default).
        $resolvedGw = $this->resolveGatewayForSessionOrder('paypal');
        $paypal_credentials = (object) [
            'paypal_client_id'    => $resolvedGw->credential('paypal_client_id'),
            'paypal_secret_key'   => $resolvedGw->credential('paypal_secret_key'),
            'paypal_app_id'       => $resolvedGw->credential('paypal_app_id'),
            'paypal_account_mode' => $resolvedGw->credential('paypal_account_mode') ?: 'sandbox',
        ];


        $after_success_url = route('payment-api.webview-success-payment',['bearer_token' => request()->bearer_token]);
        $after_failed_url = route('payment-api.webview-failed-payment');

        config(['paypal.mode' => $paypal_credentials->paypal_account_mode]);

        if ($paypal_credentials->paypal_account_mode == 'sandbox') {
            config(['paypal.sandbox.client_id' => $paypal_credentials->paypal_client_id]);
            config(['paypal.sandbox.client_secret' => $paypal_credentials->paypal_secret_key]);
        } else {
            config(['paypal.live.client_id' => $paypal_credentials->paypal_client_id]);
            config(['paypal.live.client_secret' => $paypal_credentials->paypal_secret_key]);
            config(['paypal.live.app_id' => $paypal_credentials->paypal_app_id]);
        }


        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        // Echo the order id as custom_id so the webhook can resolve the order
        // (and therefore the coach gateway) on its async notification.
        $sessionOrder = session()->get('order');
        $orderId = $sessionOrder?->id ? (string) $sessionOrder->id : null;
        $purchaseUnit = [
            'amount' => [
                'currency_code' => $payable_currency,
                'value'         => $paid_amount,
            ],
        ];
        if ($orderId) {
            $purchaseUnit['custom_id'] = $orderId;
        }

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $paypalToken = $provider->getAccessToken();
            $response = $provider->createOrder([
                'intent'              => 'CAPTURE',
                'application_context' => [
                    'return_url' => route('payment-api.paypal-success',['bearer_token' => request()->bearer_token]),
                    'cancel_url' => $after_failed_url,
                ],
                'purchase_units'      => [0 => $purchaseUnit],
            ]);
        } catch (\Exception $ex) {
            info($ex->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Payment faild, please try again',
            ], 500);
        }

        if (isset($response['id']) && $response['id'] != null) {

            Session::put('after_success_url', $after_success_url);
            Session::put('after_failed_url', $after_failed_url);
            Session::put('paypal_credentials', $paypal_credentials);

            // redirect to approve href
            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Payment faild, please try again',
            ], 400);

        } else {
            return response()->json([
                'status'  => 'error',
                'message' => 'Payment faild, please try again',
            ], 400);
        }

    }

    public function paypal_success(Request $request) {
        $paypal_credentials = Session::get('paypal_credentials');
        config(['paypal.mode' => $paypal_credentials->paypal_account_mode]);

        if ($paypal_credentials->paypal_account_mode == 'sandbox') {
            config(['paypal.sandbox.client_id' => $paypal_credentials->paypal_client_id]);
            config(['paypal.sandbox.client_secret' => $paypal_credentials->paypal_secret_key]);
        } else {
            config(['paypal.live.client_id' => $paypal_credentials->paypal_client_id]);
            config(['paypal.live.client_secret' => $paypal_credentials->paypal_secret_key]);
            config(['paypal.live.app_id' => $paypal_credentials->paypal_app_id]);
        }
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request['token']);
        if (isset($response['status']) && $response['status'] == 'COMPLETED') {
            
            Session::put('after_success_transaction', $request->PayerID);
            $after_success_url = Session::get('after_success_url');
            $paid_amount = $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['amount']['value']);
            Session::put('paid_amount', $paid_amount);

            $details = [
                'payments_captures_id' => $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['id']),
                'amount'               => $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['amount']['value']),
                'currency'             => $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']),
                'paid'                 => $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['gross_amount']['value']),
                'paypal_fee'           => $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['value']),
                'net_amount'           => $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['net_amount']['value']),
                'status'               => $this->checkArrayIsset($response['purchase_units'][0]['payments']['captures'][0]['status']),
            ];
            Session::put('payment_details', $details);
            return redirect($after_success_url);

        } else {
            $after_failed_url = Session::get('after_failed_url');
            return redirect($after_failed_url);
        }

    }
    private function checkArrayIsset($value) {
        return isset($value) ? $value : null;
    }

    /**
     * Re-resolve the gateway the session order was stamped with, fetching the
     * order FRESH from the DB so the stored provenance is read. Falls back to
     * the platform default for legacy/unstamped orders.
     */
    private function resolveGatewayForSessionOrder(string $gateway): \App\Services\Payment\ResolvedGateway
    {
        $sessionOrder = session()->get('order');
        $order = ($sessionOrder && isset($sessionOrder->id))
            ? \Modules\Order\app\Models\Order::find($sessionOrder->id)
            : null;
        if (! $order) {
            $order = new \Modules\Order\app\Models\Order(['payment_method' => $gateway]);
        }
        return app(\App\Services\Payment\PaymentGatewayResolverService::class)->resolveForOrder($order);
    }
}
