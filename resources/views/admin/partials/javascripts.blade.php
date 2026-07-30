<script src="{{ asset('global/js/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('backend/js/popper.min.js') }}"></script>
<script src="{{ asset('backend/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('backend/js/jquery.nicescroll.min.js') }}"></script>
<script src="{{ asset('backend/js/moment.min.js') }}"></script>
<script src="{{ asset('backend/js/stisla.js') }}"></script>
<script src="{{ asset('backend/js/scripts.js') }}?v={{$setting?->version}}"></script>
<script src="{{ asset('backend/js/select2.min.js') }}"></script>
<script src="{{ asset('backend/js/tagify.js') }}"></script>
<script src="{{ asset('global/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('backend/js/bootstrap4-toggle.min.js') }}"></script>
<script src="{{ asset('backend/js/fontawesome-iconpicker.min.js') }}"></script>
<script src="{{ asset('backend/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('backend/clockpicker/dist/bootstrap-clockpicker.js') }}"></script>
<script src="{{ asset('backend/datetimepicker/jquery.datetimepicker.js') }}"></script>
<script src="{{ asset('backend/js/iziToast.min.js') }}"></script>
<script src="{{ asset('backend/js/modules-toastr.js') }}?v={{$setting?->version}}"></script>
<script src="{{ asset('backend/tinymce/js/tinymce/tinymce.min.js') }}"></script>
<script src="{{ asset('global/nice-select/jquery.nice-select.min.js') }}"></script>
<script src="{{ asset('backend/js/default/backend.js') }}?v={{$setting?->version}}"></script>
<script src="{{ asset('backend/js/custom.js') }}?v={{$setting?->version}}"></script>

<!-- File Manager js-->
<script src="{{ url('/vendor/laravel-filemanager/js/stand-alone-button.js') }}"></script>

<script>
    $('.file-manager').filemanager('file', {prefix: '{{ url("/laravel-filemanager") }}'});
    $('.file-manager-image').filemanager('image', {prefix: '{{ url("/laravel-filemanager") }}'});
</script>

<script>
    // Audit fix H2/H3 (2026-05-12) — read either the legacy `messege`
    // typo key OR the correctly spelled `message` key, plus the simple
    // 'success'/'error'/'info'/'warning' top-level keys some controllers
    // use directly. Without this, half of redirect()->with('success', ...)
    // callers rendered nothing because the renderer only checked `messege`.
    @php
        $__flashTypes = ['success', 'error', 'warning', 'info'];
        $__flashType  = (string) session('alert-type', '');
        $__flashMsg   = session('messege') ?? session('message');
        if (!$__flashMsg) {
            foreach ($__flashTypes as $__t) {
                if (session()->has($__t)) {
                    $__flashType = $__t;
                    $__flashMsg  = session($__t);
                    break;
                }
            }
        }
        if (!$__flashType && $__flashMsg) {
            $__flashType = 'info';
        }
    @endphp
    @if ($__flashMsg)
        switch ("{{ $__flashType }}") {
            case 'success':
                toastr.success(@json($__flashMsg));
                break;
            case 'warning':
                toastr.warning(@json($__flashMsg));
                break;
            case 'error':
            case 'danger':
                toastr.error(@json($__flashMsg));
                break;
            default:
                toastr.info(@json($__flashMsg));
        }
    @endif

    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        orientation: "bottom auto"
    });
</script>

@if ($errors->any())
    @foreach ($errors->all() as $error)
        <script>
            toastr.error(@json($error));
        </script>
    @endforeach
@endif
