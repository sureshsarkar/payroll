<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Marcadopago Checkout</title>
</head>

<body>
    @php
        $paymentUrl = route('pay.mercadopago.preference');
        $failedUrl = route('payment-failed');
    @endphp
    <script src="{{ asset('global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="https://sdk.mercadopago.com/js/v2"></script>

    <script>
        "use strict";
        (function($) {
            "use strict";
            $(document).ready(function() {
                payment();
            });
        })(jQuery);

        function payment() {
            var paymentUrl = "{!! $paymentUrl !!}";
            const mp = new MercadoPago("{{ cache('mercadopagoConfig')->public_key }}");
            fetch("{!! $paymentUrl !!}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({})
                })
                .then(response => {
                    return response.json();
                })
                .then(preference => {
                    if (preference.error) {
                        throw new Error(preference.error);
                    }
                    window.location.href = preference.init_point;
                    mp.checkout({
                        preference: {
                            id: preference.id
                        },
                        autoOpen: true
                    });
                })
                .catch(error => window.location.href = "{{ $failedUrl }}");
        }
    </script>
</body>

</html>
