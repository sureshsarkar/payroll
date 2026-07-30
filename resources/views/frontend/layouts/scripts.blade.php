<script src="{{ asset('global/js/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('frontend/js/proper.min.js') }}"></script>
<script src="{{ asset('frontend/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('frontend/js/imagesloaded.pkgd.min.js') }}"></script>
<script src="{{ asset('frontend/js/jquery.magnific-popup.min.js') }}"></script>
<script src="{{ asset('frontend/js/jquery.odometer.min.js') }}"></script>
<script src="{{ asset('frontend/js/jquery.appear.js') }}"></script>
<script src="{{ asset('frontend/js/tween-max.min.js') }}"></script>
<script src="{{ asset('frontend/js/select2.min.js') }}"></script>
<script src="{{ asset('frontend/js/swiper-bundle.min.js') }}"></script>
<script src="{{ asset('frontend/js/jquery.marquee.min.js') }}"></script>
@if ($setting?->cursor_dot_status == 'active')
    <script src="{{ asset('frontend/js/tg-cursor.min.js') }}"></script>
@endif
<script src="{{ asset('frontend/js/svg-inject.min.js') }}"></script>
<script src="{{ asset('frontend/js/jquery.circleType.js') }}"></script>
<script src="{{ asset('frontend/js/jquery.lettering.min.js') }}"></script>
<script src="{{ asset('frontend/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('frontend/js/plyr.min.js') }}"></script>
<script src="{{ asset('frontend/js/wow.min.js') }}"></script>
<script src="{{ asset('frontend/js/aos.js') }}"></script>
<script src="{{ asset('frontend/js/vivus.min.js') }}"></script>
<script src="{{ asset('global/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('frontend/js/sweetalert.js') }}"></script>
<script src="{{ asset('frontend/js/default/frontend.js') }}?v={{ @filemtime(public_path('frontend/js/default/frontend.js')) ?: $setting?->version }}"></script>
{{-- 2026-06-16 — cache-bust on the FILE's mtime so a redeployed cart.js is fetched
     immediately. The old ?v=setting.version only changed on a settings edit, leaving
     a stale cart.js cached after deploys → the batch modal opened empty. --}}
<script src="{{ asset('frontend/js/default/cart.js') }}?v={{ @filemtime(public_path('frontend/js/default/cart.js')) ?: $setting?->version }}"></script>
<script src="{{ asset('global/nice-select/jquery.nice-select.min.js') }}"></script>
<!-- File Manager js-->
<script src="{{ url('/vendor/laravel-filemanager/js/stand-alone-button.js') }}"></script>


<script src="{{ asset('frontend/js/main.js') }}?v={{ @filemtime(public_path('frontend/js/main.js')) ?: $setting?->version }}"></script>

<script>
    $('.file-manager').filemanager('file', {
        prefix: '{{ url('/frontend-filemanager') }}'
    });
    $('.file-manager-image').filemanager('image', {
        prefix: '{{ url('/frontend-filemanager') }}'
    });

    SVGInject(document.querySelectorAll("img.injectable"));
</script>

<!-- dynamic Toastr Notification -->
<script>
    "use strict";
    toastr.options.closeButton = true;
    toastr.options.progressBar = true;
    toastr.options.positionClass = 'toast-bottom-right';

    @session('messege')
    var type = "{{ Session::get('alert-type', 'info') }}"
    switch (type) {
        case 'info':
            toastr.info("{{ $value }}");
            break;
        case 'success':
            toastr.success("{{ $value }}");
            break;
        case 'warning':
            toastr.warning("{{ $value }}");
            break;
        case 'error':
            toastr.error("{{ $value }}");
            break;
    }
    @endsession

    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        orientation: "bottom auto"
    });
</script>


<!-- Toastr -->
@if ($errors->any())
    @foreach ($errors->all() as $error)
        <script>
            toastr.error('{{ $error }}', null, {
                timeOut: 10000
            });
        </script>
    @endforeach
@endif


<!-- Google reCAPTCHA -->
@if (Cache::get('setting')->recaptcha_status === 'active')
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif

<!-- tawk -->
@if ($setting->tawk_status == 'active')
    <script type="text/javascript">
        "use strict";
        var Tawk_API = Tawk_API || {},
            Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = '{{ $setting->tawk_chat_link }}';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
@endif

<!-- Cookie Consent -->
@if ($setting->cookie_status == 'active')
    <script src="{{ asset('frontend/js/cookieconsent.min.js') }}"></script>

    <script>
        "use strict";
        window.addEventListener("load", function() {
            window.wpcc.init({
                "border": "{{ $setting->border }}",
                "corners": "{{ $setting->corners }}",
                {{-- 2026-07-04 — brand-consistent cookie banner. The stored
                     theme colours (background_color = #184dec bright blue) clashed
                     with the emerald brand on every page, so the popup uses a
                     professional dark ground with an emerald accept button. --}}
                "colors": {
                    "popup": {
                        "background": "#0f172a",
                        "text": "#e2e8f0 !important",
                        "border": "#1e293b"
                    },
                    "button": {
                        "background": "#10b981",
                        "text": "#ffffff"
                    }
                },
                "content": {
                    "href": "{{ url($setting->link) }}",
                    "message": "{{ $setting->message }}",
                    "link": "{{ $setting->link_text }}",
                    "button": "{{ $setting->btn_text }}"
                }
            })
        });
    </script>
@endif

<script>
    if ($(".marquee_mode").length) {
        $('.marquee_mode').marquee({
            speed: 20,
            gap: 35,
            delayBeforeStart: 0,
            direction: "{{ Session::has('text_direction') && Session::get('text_direction') == 'rtl' ? 'right' : 'left' }}",
            duplicated: true,
            pauseOnHover: true,
            startVisible: true,
        });
    }
</script>

<script>
    $(document).on("click", '.wpcc-btn', function() {
        $('.wpcc-container').fadeOut(1000);
    });
</script>
