<?php

namespace Modules\CryptoPayment\app\Http\Controllers;

// use autoload from vendor
require base_path('Modules/CryptoPayment/vendor/autoload.php');

use App\Http\Controllers\Controller;
use CoinGate\Client;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CoinGateController extends Controller
{
    public $tbPayment;

    /**
     * Display a listing of the resource.
     */
    public function createPayment()
    {
        $currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        $after_failed_url = route('payment-failed');

        $order = session()->get('order');

        $order_id = $order->invoice_id;

        // Create a client instance
        $client = $this->createCoinGateClient();
        $token = hash('sha512', 'coingate' . rand());

        $cryptoConfig = cache('cryptoConfig');
        $params = [
            'order_id' => $order_id,
            'price_amount' => $paid_amount,
            'price_currency' => $currency,
            'receive_currency' => $cryptoConfig->crypto_receive_currency ?? 'USD',
            'callback_url' => url('coin-gate/callback') . '?token=' . $token,
            'cancel_url' => $after_failed_url,
            'success_url' => url('coin-gate/success'),
            'title' => 'Order Payment',
            'description' => "Payment for order #$order_id",
        ];


        try {
            $order = $client->order->create($params);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }


        if (isset($order->payment_url)) {
            // Store the token and Order ID for verification later
            session(['coin_gate_token' => $token, 'coin_gate_order_id' => $order->id]);

            // Redirect to the CoinGate payment URL
            return redirect($order->payment_url);
        } else {
            // Handle error creating the order
            return redirect($after_failed_url);
        }
    }

    public function success(Request $request)
    {
        $order_id = session('coin_gate_order_id');

        $order_details = [];
        if ($order_id) {
            $client = $this->createCoinGateClient();
            $order_details = $client->order->get($order_id);
            session()->forget(['coin_gate_token', 'coin_gate_order_id']);
        }

        $paymentDetails = [
            'transaction_id' => $order_details->uuid,
            'amount'         => $order_details->price_amount,
            'currency'       => $order_details->price_currency,
            'payment_status' => $order_details->paid_at ? 'success' : 'pending',
            'created'        => $order_details->paid_at,
        ];
        $after_success_url = route('payment-success');

        session()->put('after_success_url', $after_success_url);
        session()->put('after_success_transaction', $order_details?->uuid);
        session()->put('payment_details', $paymentDetails);


        return redirect($after_success_url);
    }
    private function createCoinGateClient()
    {
        $config = cache()->get('cryptoConfig');

        return new Client($config?->crypto_token, $config?->crypto_sandbox ?? false); // 'true' for sandbox mode, 'false' for live mode
    }
}
