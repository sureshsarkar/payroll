@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap">

    {{-- Header ───────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="title mb-1" style="color:#1c1a4a; font-weight:700;">
                <i class="fas fa-receipt me-1" style="color:#10b981;"></i> {{ __('Transactions') }}
            </h4>
            <small class="text-muted">{{ __('All fee payments received from students.') }}</small>
        </div>
        <a href="{{ route('instructor.fees.index') }}" class="btn btn-light btn-hight-basic">
            <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Fee Management') }}
        </a>
    </div>

    {{-- KPI Strip ────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-sm-6">
            <div class="card" style="padding:18px; border-left:4px solid #10b981; border-radius:10px;">
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Total Collected') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">{{ currency($kpi['total_collected']) }}</div>
                <div style="font-size:11px; color:#6b7280;">{{ $kpi['paid_count'] }} {{ __('payments') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card" style="padding:18px; border-left:4px solid #10b981; border-radius:10px;">
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Successful') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">{{ number_format($kpi['paid_count']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card" style="padding:18px; border-left:4px solid #ef4444; border-radius:10px;">
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Failed') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">{{ number_format($kpi['failed_count']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card" style="padding:18px; border-left:4px solid #9ca3af; border-radius:10px;">
                <div style="font-size:10px; text-transform:uppercase; color:#9ca3af; font-weight:600; letter-spacing:.4px;">
                    {{ __('Refunded') }}
                </div>
                <div style="font-size:24px; font-weight:800; color:#1c1a4a; margin-top:4px;">{{ number_format($kpi['refunded_count']) }}</div>
                <div style="font-size:11px; color:#6b7280;">{{ currency($kpi['refunded_amount']) }}</div>
            </div>
        </div>
    </div>

    {{-- Filter row ───────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('instructor.fees.transactions') }}"
          class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        @php $st = request('status'); @endphp
        @foreach (['' => __('All'), 'paid' => __('Paid'), 'failed' => __('Failed'), 'refunded' => __('Refunded'), 'initiated' => __('Initiated')] as $val => $label)
            <a href="{{ route('instructor.fees.transactions', array_filter(['status' => $val, 'q' => request('q')])) }}"
               class="btn btn-sm @if (($st ?? '') === $val) btn-primary @else btn-light @endif"
               style="border-radius:999px; padding:6px 16px; font-size:12px;">
                {{ $label }}
            </a>
        @endforeach
        <div class="flex-grow-1"></div>
        <input type="search" name="q" value="{{ request('q') }}"
               class="form-control form-control-sm" style="max-width:240px;"
               placeholder="{{ __('Search student…') }}">
        <button type="submit" class="btn btn-light btn-sm">
            <i class="fas fa-search"></i> {{ __('Search') }}
        </button>
    </form>

    {{-- Transactions table ───────────────────────────────────── --}}
    <div class="dashboard__review-table table-responsive"
         style="background:#fff; border-radius:10px; padding:8px;">
        <table class="table table-borderless align-middle">
            <thead>
                <tr style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.3px;">
                    <th>{{ __('Receipt') }}</th>
                    <th>{{ __('Student') }}</th>
                    <th>{{ __('Fee') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Gateway') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $p)
                    @php
                        $statusPill = match ($p->status) {
                            'paid'      => ['#ecfdf5', '#047857', __('Paid')],
                            'failed'    => ['#fef2f2', '#991b1b', __('Failed')],
                            'refunded'  => ['#f3f4f6', '#4b5563', __('Refunded')],
                            'initiated' => ['#fffbeb', '#92400e', __('Initiated')],
                            default     => ['#f3f4f6', '#4b5563', $p->status],
                        };
                    @endphp
                    <tr style="border-top:1px solid #f3f4f6;">
                        <td>
                            <code style="font-size:10px; color:#1c1a4a;">{{ $p->receipt_no }}</code>
                        </td>
                        <td>
                            <div style="font-weight:600; color:#1f2937;">{{ $p->student?->name ?? '—' }}</div>
                            <small class="text-muted">{{ $p->student?->email }}</small>
                        </td>
                        <td>
                            <div style="font-size:13px;">{{ \Illuminate\Support\Str::limit($p->demand?->title ?? '—', 28) }}</div>
                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($p->demand?->batch?->title ?? '—', 24) }}</small>
                        </td>
                        <td class="text-end" style="font-weight:600;">{{ currency($p->amount) }}</td>
                        <td>
                            <span class="text-uppercase" style="font-size:11px; font-weight:600; color:#6b7280;">
                                {{ $p->gateway }}
                            </span>
                        </td>
                        <td>
                            <span style="background:{{ $statusPill[0] }}; color:{{ $statusPill[1] }};
                                         padding:3px 10px; border-radius:999px;
                                         font-size:10px; font-weight:600;">
                                {{ $statusPill[2] }}
                            </span>
                        </td>
                        <td>
                            <small>{{ optional($p->paid_at ?? $p->created_at)->format('M j, Y') }}</small>
                        </td>
                        <td>
                            @if ($p->status === 'paid')
                                <form method="POST" action="{{ route('instructor.fees.payments.refund', $p->id) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('{{ __('Mark this payment as refunded?') }}');">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" style="font-size:11px; padding:2px 10px; border-radius:999px;">
                                        <i class="fas fa-undo"></i> {{ __('Refund') }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-receipt fa-2x mb-2" style="color:#d1d5db;"></i>
                            <div>{{ __('No transactions yet.') }}</div>
                            <small>{{ __('Recorded payments will appear here.') }}</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($payments->hasPages())
            <div class="px-2 py-2">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
@endsection
