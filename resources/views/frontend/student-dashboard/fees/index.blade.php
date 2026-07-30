@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap mt-3">
    <div class="dashboard__content-title d-flex justify-content-between align-items-center">
        <h4 class="title"><i class="fas fa-coins" style="color:#f59e0b;"></i> {{ __('My Fees') }}</h4>
        <small class="text-muted">{{ __('All fee demands from your batches') }}</small>
    </div>

    {{-- KPI strip ─────────────────────────────────────────────── --}}
    @php
        $totalDue = $demands->getCollection()->where('ui_status', '!=', 'paid')->sum('ui_owed');
        $totalPaid = $demands->getCollection()->where('ui_status', 'paid')->sum('amount');
        $overdueCount = $demands->getCollection()->where('ui_status', 'overdue')->count();
    @endphp
    <div class="row g-2 mb-3">
        <div class="col-sm-4 col-12">
            <div class="card text-center" style="padding:14px; border-left:4px solid #ef4444;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Total Due') }}</div>
                <div style="font-size:22px; font-weight:700; color:#1f2937;">{{ currency($totalDue) }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-12">
            <div class="card text-center" style="padding:14px; border-left:4px solid #10b981;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Paid So Far') }}</div>
                <div style="font-size:22px; font-weight:700; color:#10b981;">{{ currency($totalPaid) }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-12">
            <div class="card text-center" style="padding:14px; border-left:4px solid #f59e0b;">
                <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('Overdue') }}</div>
                <div style="font-size:22px; font-weight:700; color:#f59e0b;">{{ number_format($overdueCount) }}</div>
            </div>
        </div>
    </div>

    {{-- Demands table ───────────────────────────────────────── --}}
    <div class="dashboard__review-table table-responsive" style="background:#fff; border-radius:8px; padding:8px;">
        <table class="table table-borderless align-middle">
            <thead>
                <tr>
                    <th>{{ __('Fee') }}</th>
                    <th>{{ __('Batch') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Due') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($demands as $d)
                    @php
                        [$pillBg, $pillFg, $pillTxt] = match ($d->ui_status) {
                            'paid'    => ['#ecfdf5', '#047857', __('Paid')],
                            'overdue' => ['#fef2f2', '#991b1b', __('Overdue')],
                            default   => ['#fffbeb', '#92400e', __('Due')],
                        };
                    @endphp
                    <tr style="border-top:1px solid #f3f4f6;">
                        <td>
                            <div style="font-weight:600; color:#1f2937;">{{ \Illuminate\Support\Str::limit($d->title, 40) }}</div>
                            @if ($d->notes)
                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($d->notes, 60) }}</small>
                            @endif
                        </td>
                        <td>
                            <div style="font-size:13px;">{{ \Illuminate\Support\Str::limit($d->batch?->title ?? '—', 28) }}</div>
                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($d->batch?->course?->title ?? '', 30) }}</small>
                        </td>
                        <td class="text-end" style="font-weight:600;">{{ currency($d->amount) }}</td>
                        <td>
                            @if ($d->due_date)
                                <small>{{ $d->due_date->format('M j, Y') }}</small>
                            @else
                                <small class="text-muted">—</small>
                            @endif
                        </td>
                        <td>
                            <span style="background:{{ $pillBg }}; color:{{ $pillFg }};
                                         padding:3px 10px; border-radius:999px;
                                         font-size:10px; font-weight:600;">{{ $pillTxt }}</span>
                        </td>
                        <td class="text-end">
                            @if ($d->ui_status !== 'paid')
                                <button type="button"
                                        class="btn btn-sm btn-primary fee-pay-btn"
                                        data-demand-id="{{ $d->id }}"
                                        data-demand-title="{{ $d->title }}"
                                        data-amount="{{ $d->amount }}">
                                    <i class="fas fa-credit-card me-1"></i>{{ __('Pay Now') }}
                                </button>
                            @else
                                <i class="fas fa-check-circle" style="color:#10b981;"></i>
                                <small class="text-muted">{{ __('Paid') }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-receipt fa-2x mb-2" style="color:#d1d5db;"></i>
                            <div>{{ __('No fee demands on file.') }}</div>
                            <small>{{ __('Fees raised by your coach will appear here.') }}</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($demands->hasPages())
            <div class="mt-3">{{ $demands->links() }}</div>
        @endif
    </div>
</div>

{{-- Razorpay JS ────────────────────────────────────────────────── --}}
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const csrf = '{{ csrf_token() }}';

    document.querySelectorAll('.fee-pay-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const demandId = btn.dataset.demandId;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>{{ __("Loading…") }}';

            fetch('{{ url('/student/fees') }}/' + demandId + '/checkout', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (result) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (!result.ok) {
                    alert(result.data.error || '{{ __("Could not start payment. Try again.") }}');
                    return;
                }
                const opts = {
                    key:         result.data.razorpay_key,
                    amount:      result.data.amount,
                    currency:    result.data.currency,
                    name:        result.data.name,
                    description: result.data.description,
                    order_id:    result.data.razorpay_order_id,
                    prefill:     result.data.prefill,
                    theme:       { color: '#10b981' },
                    handler: function (response) {
                        // Confirm with server.
                        fetch('{{ route('student.fees.verify') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                razorpay_order_id:   response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature:  response.razorpay_signature,
                            }),
                        })
                        .then(r => r.json())
                        .then(verifyRes => {
                            if (verifyRes.status === 'ok') {
                                alert('{{ __("Payment received. Receipt: ") }}' + verifyRes.receipt_no);
                                window.location.reload();
                            } else {
                                alert(verifyRes.error || '{{ __("Verification failed. Please contact support.") }}');
                            }
                        });
                    },
                    modal: {
                        ondismiss: function () {
                            console.log('Razorpay checkout dismissed.');
                        }
                    },
                };
                const rzp = new Razorpay(opts);
                rzp.on('payment.failed', function (resp) {
                    alert('{{ __("Payment failed: ") }}' + (resp.error?.description ?? ''));
                });
                rzp.open();
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert('{{ __("Network error — please try again.") }}');
            });
        });
    });
})();
</script>
@endsection
