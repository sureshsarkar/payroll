<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Mollie Checkout</title>
</head>

<body>
    <a href="{{ isset($token) ? route('payment-api.mollie-webview', ['bearer_token' => $token, 'order_id' => $order_id]) : route('pay-via-mollie') }}" id="btn"></a>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('btn').click();
        });
    </script>
</body>

</html>
