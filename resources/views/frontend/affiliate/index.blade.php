@extends('frontend.layouts.master')

@section('contents')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap:12px;">
                <div>
                    <h2 style="margin:0; color:#1c1a4a;">
                        <i class="fas fa-share-alt" style="color:#10b981; margin-right:8px;"></i>
                        {{ __('Affiliate Program') }}
                    </h2>
                    <p style="color:#6b7280; margin:4px 0 0; font-size:14px;">
                        {{ __('Earn') }} <strong>{{ $referralPercent }}%</strong> {{ __('on every paid order from people you refer.') }}
                    </p>
                </div>
            </div>

            {{-- Referral link card --}}
            <div style="background:linear-gradient(135deg,#10b981,#7c3aed); border-radius:14px; padding:24px; color:#fff; margin-bottom:24px;">
                <div style="font-size:11px; opacity:0.85; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
                    {{ __('Your unique referral link') }}
                </div>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <input type="text" id="mbsRefLink" value="{{ $referralUrl }}" readonly
                           style="flex:1; min-width:250px; padding:12px 16px; background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.3); border-radius:8px; color:#fff; font-family:monospace; font-size:14px; font-weight:500;">
                    <button id="mbsRefCopy" type="button"
                            style="padding:12px 20px; background:#fff; color:#10b981; border:none; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer; white-space:nowrap;">
                        <i class="fas fa-copy"></i> {{ __('Copy') }}
                    </button>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
                    <a href="https://twitter.com/intent/tweet?text={{ urlencode('Check out '.config('app.name').' — '.$referralUrl) }}" target="_blank" rel="noopener"
                       style="padding:8px 14px; background:rgba(255,255,255,0.15); color:#fff; border-radius:6px; font-size:12px; text-decoration:none;">
                        <i class="fab fa-twitter"></i> {{ __('Tweet') }}
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($referralUrl) }}" target="_blank" rel="noopener"
                       style="padding:8px 14px; background:rgba(255,255,255,0.15); color:#fff; border-radius:6px; font-size:12px; text-decoration:none;">
                        <i class="fab fa-facebook"></i> {{ __('Facebook') }}
                    </a>
                    <a href="https://wa.me/?text={{ urlencode('Check out '.config('app.name').' — '.$referralUrl) }}" target="_blank" rel="noopener"
                       style="padding:8px 14px; background:rgba(255,255,255,0.15); color:#fff; border-radius:6px; font-size:12px; text-decoration:none;">
                        <i class="fab fa-whatsapp"></i> {{ __('WhatsApp') }}
                    </a>
                    <a href="mailto:?subject={{ urlencode('Try '.config('app.name')) }}&body={{ urlencode($referralUrl) }}"
                       style="padding:8px 14px; background:rgba(255,255,255,0.15); color:#fff; border-radius:6px; font-size:12px; text-decoration:none;">
                        <i class="fas fa-envelope"></i> {{ __('Email') }}
                    </a>
                </div>
            </div>

            {{-- KPIs --}}
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
                <div style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
                    <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">{{ __('Lifetime earned') }}</div>
                    <div style="font-size:24px; font-weight:700; color:#1c1a4a;">{{ currency($totals['lifetime']) }}</div>
                </div>
                <div style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
                    <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">{{ __('Eligible (awaiting credit)') }}</div>
                    <div style="font-size:24px; font-weight:700; color:#f59e0b;">{{ currency($totals['eligible'] + $totals['approved']) }}</div>
                </div>
                <div style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
                    <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">{{ __('Credited') }}</div>
                    <div style="font-size:24px; font-weight:700; color:#10b981;">{{ currency($totals['credited']) }}</div>
                </div>
                <div style="background:#fff; padding:18px 20px; border-radius:12px; border:1px solid #e5e7eb;">
                    <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">{{ __('Referrals') }}</div>
                    <div style="font-size:24px; font-weight:700; color:#10b981;">{{ $referredCount }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:4px;">{{ __('signed up') }}</div>
                </div>
            </div>

            {{-- Recent commissions --}}
            <div style="background:#fff; border-radius:12px; border:1px solid #e5e7eb; overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #f3f4f6;">
                    <h4 style="margin:0; font-size:15px; color:#1c1a4a;">{{ __('Recent commissions') }}</h4>
                </div>
                @if ($recentCommissions->isEmpty())
                    <div style="padding:40px 20px; text-align:center; color:#9ca3af; font-size:13px;">
                        <i class="fas fa-receipt" style="font-size:36px; display:block; margin-bottom:12px; opacity:0.4;"></i>
                        {{ __('No commissions yet — share your link to start earning.') }}
                    </div>
                @else
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#fafbfc; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">
                                <th style="padding:10px 16px; text-align:left;">{{ __('Date') }}</th>
                                <th style="padding:10px 16px; text-align:left;">{{ __('Referred') }}</th>
                                <th style="padding:10px 16px; text-align:left;">{{ __('Order') }}</th>
                                <th style="padding:10px 16px; text-align:right;">{{ __('Amount') }}</th>
                                <th style="padding:10px 16px; text-align:center;">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentCommissions as $c)
                                @php
                                    $colors = [
                                        'eligible' => ['bg' => '#fef3c7', 'fg' => '#92400e'],
                                        'approved' => ['bg' => '#dbeafe', 'fg' => '#1e3a8a'],
                                        'credited' => ['bg' => '#dcfce7', 'fg' => '#166534'],
                                        'rejected' => ['bg' => '#f3f4f6', 'fg' => '#6b7280'],
                                        'reversed' => ['bg' => '#fee2e2', 'fg' => '#991b1b'],
                                    ];
                                    $col = $colors[$c->status] ?? ['bg' => '#f3f4f6', 'fg' => '#6b7280'];
                                @endphp
                                <tr style="border-top:1px solid #f3f4f6;">
                                    <td style="padding:12px 16px; font-size:13px; color:#6b7280;">{{ $c->created_at->format('M j, Y') }}</td>
                                    <td style="padding:12px 16px; font-size:13px; color:#1c1a4a;">{{ $c->referred?->name ?? '—' }}</td>
                                    <td style="padding:12px 16px; font-size:12px; color:#6b7280; font-family:monospace;">#{{ $c->order?->invoice_id ?? $c->order_id }}</td>
                                    <td style="padding:12px 16px; font-size:13px; font-weight:600; color:#1c1a4a; text-align:right;">{{ currency($c->amount) }}</td>
                                    <td style="padding:12px 16px; text-align:center;">
                                        <span style="padding:4px 10px; background:{{ $col['bg'] }}; color:{{ $col['fg'] }}; border-radius:4px; font-size:11px; font-weight:600; text-transform:uppercase;">
                                            {{ $c->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('mbsRefCopy')?.addEventListener('click', () => {
    const input = document.getElementById('mbsRefLink');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('mbsRefCopy');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> {{ __('Copied!') }}';
        setTimeout(() => { btn.innerHTML = orig; }, 1500);
    });
});
</script>
@endsection
