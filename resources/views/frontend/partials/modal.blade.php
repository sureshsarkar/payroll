<!-- Modal -->
<div class="modal fade dynamic-modal modal-lg" tabindex="-1" aria-labelledby="dynamic-modalLabel" aria-hidden="true" data-bs-backdrop='static'>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="d-flex justify-content-center align-items:center p-3">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">{{ __('Loading') }}...</span>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade bd-example-modal-lg" id="iframeModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <h5>{{__('Your are using this website under an external iframe')}}</h5>
                <p>{{__('For a better experience please browse directly instead of an external iframe')}}</p>
            </div>
            <div class="modal-footer justify-content-center">
                <a href="{{url('/')}}" class="btn btn-sm btn-primary">{{__('Browse Directly')}}</a>
            </div>
        </div>
    </div>
</div>