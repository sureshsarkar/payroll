<?php

namespace Modules\MercadoPagoPG\app\Http\Controllers;

require_once base_path('Modules/MercadoPagoPG/vendor/autoload.php');

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;


use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    public function createPreference(Request $request)
    {
        $config = Cache::get('mercadopagoConfig');
        MercadoPagoConfig::setAccessToken($config->access_token);
        MercadoPagoConfig::setRuntimeEnviroment($config->mercadopago_sandbox == 1 ? MercadoPagoConfig::LOCAL : MercadoPagoConfig::SERVER);

        $payer = [
            "name" => userAuth()->name,
            "surname" => '',
            "email" => userAuth()->email,
        ];

        $requestData = $this->createPreferenceRequest($payer);

        $client = new PreferenceClient();
        try {
            $preference = $client->create($requestData);

            return response()->json([
                'id' => $preference->id,
                'init_point' => $preference->init_point,
            ]);
        } catch (MPApiException $error) {
            return response()->json([
                'error' => $error->getApiResponse()->getContent(),
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createPreferenceRequest($payer)
    {
        $paymentMethods = [
            "excluded_payment_methods" => [],
            "installments" => 1,
            "default_installments" => 1

        ];

        $after_success_url = route('mercadopago.success');
        $after_failed_url = route('mercadopago.failed');

        $backUrls = [
            'success' => $after_success_url,
            'failure' => $after_failed_url
        ];


        // external_reference must hold the local Order id so the webhook can
        // re-query MercadoPago and resolve which order to mark paid.
        $sessionOrder = session()->get('order');
        $orderId = $sessionOrder?->id ? (string) $sessionOrder->id : 'unknown';

        $request = [
            "items" => $this->orderData(),
            "payer" => $payer,
            "payment_methods" => $paymentMethods,
            "back_urls" => $backUrls,
            "statement_descriptor" => "Mercado Pago",
            "external_reference" => $orderId,
            "expires" => false,
            "auto_return" => 'approved',

        ];
        return $request;
    }

    private function orderData(){
        $order = session()->get('order');
        $order_id = $order->invoice_id;
        $data = [];
        $data[] = [
            'title' => 'Order Payment',
            'unit_price' => (float) session()->get('paid_amount'),
            'quantity' => 1,
            'currency_id' => session('payable_currency'),
            'description' => "Payment for order #$order_id"
        ];
        return $data;
    }

    public function success(Request $request)
    {
        $paymentDetails = [
            'transaction_id' => $request->payment_id,
            'amount'         => session('paid_amount'),
            'currency'       => session('payable_currency'),
            'payment_status' => $request->status == 'approved' ? 'success' : 'pending',
            'created'        => now()->toDateTimeString(),
        ];

        $after_success_url = route('payment-success');

        session()->put('after_success_url', $after_success_url);
        session()->put('after_success_transaction', $request->payment_id);
        session()->put('payment_details', $paymentDetails);

        return redirect($after_success_url);
    }

    public function failed(Request $request)
    {
        return redirect(route('payment-failed'));
    }
}
