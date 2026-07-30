<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Stripe Checkout</title>
</head>

<body>
    <form id="stripe-form"
        action="{{ isset($token) ? route('payment-api.stripe-webview', ['bearer_token' => $token, 'order_id' => $order_id]) : route('pay-via-stripe') }}"
        method="POST" class="d-none">
        @csrf
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('stripe-form').submit();
        });
    </script>
</body>

</html>