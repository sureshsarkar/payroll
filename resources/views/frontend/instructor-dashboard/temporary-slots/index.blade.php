@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page" id="tempSlots">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-calendar-day"></i> {{ __('Temporary Slots') }}</h4>
            <p>{{ __('Date-specific batch attendance. A student attends another batch for one day; their primary batch stays unchanged.') }}</p>
        </div>
    </div>

    @if(session('messege'))
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13.5px;">{{ session('messege') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;">
        <div class="corp-form-card"><div class="corp-form-card__body"><div style="font-size:12.5px;color:#64748b;">{{ __('Upcoming') }}</div><div style="font-size:24px;font-weight:700;color:#059669;">{{ $kpis['upcoming'] }}</div></div></div>
        <div class="corp-form-card"><div class="corp-form-card__body"><div style="font-size:12.5px;color:#64748b;">{{ __('Scheduled (all)') }}</div><div style="font-size:24px;font-weight:700;color:#1e293b;">{{ $kpis['scheduled'] }}</div></div></div>
    </div>

    <form method="GET" class="corp-form-card" style="margin-bottom:14px;">
        <div class="corp-form-card__body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;">
            <label style="font-size:12px;color:#475569;">{{ __('Search') }}<input name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('Student name / email') }}" style="margin-top:4px;"></label>
            <label style="font-size:12px;color:#475569;">{{ __('Status') }}
                <select name="status" class="form-control form-control-sm" style="margin-top:4px;">
                    <option value="">{{ __('All') }}</option>
                    <option value="scheduled" {{ $status==='scheduled'?'selected':'' }}>{{ __('Scheduled') }}</option>
                    <option value="cancelled" {{ $status==='cancelled'?'selected':'' }}>{{ __('Cancelled') }}</option>
                </select>
            </label>
            <label style="font-size:12px;color:#475569;">{{ __('From') }}<input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm" style="margin-top:4px;"></label>
            <label style="font-size:12px;color:#475569;">{{ __('To') }}<input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm" style="margin-top:4px;"></label>
            <div style="display:flex;gap:6px;">
                <button class="btn-corp-primary" style="white-space:nowrap;"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
                <a href="{{ route('instructor.temporary-slots.index') }}" class="btn-corp-secondary">{{ __('Reset') }}</a>
            </div>
        </div>
    </form>

    <div class="corp-form-card">
        <div class="corp-form-card__body" style="overflow-x:auto;">
            @if($slots->count())
                <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:720px;">
                    <thead><tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Date') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Student') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Course') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Home → Attends') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Reason') }}</th>
                        <th style="padding:9px 8px;font-weight:600;">{{ __('Status') }}</th>
                        <th style="padding:9px 8px;font-weight:600;"></th>
                    </tr></thead>
                    <tbody>
                        @foreach($slots as $s)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 8px;white-space:nowrap;font-weight:500;color:#1e293b;">{{ optional($s->slot_date)->format('d M Y') }}</td>
                                <td style="padding:10px 8px;"><div style="color:#1e293b;">{{ optional($s->student)->name }}</div><div style="color:#94a3b8;font-size:11.5px;">{{ optional($s->student)->email }}</div></td>
                                <td style="padding:10px 8px;color:#475569;">{{ optional($s->course)->title }}</td>
                                <td style="padding:10px 8px;color:#475569;">{{ optional($s->primaryBatch)->title ?? __('—') }} <i class="fas fa-arrow-right" style="font-size:10px;color:#cbd5e1;"></i> <strong>{{ optional($s->targetBatch)->title }}</strong></td>
                                <td style="padding:10px 8px;color:#64748b;">{{ $s->reason ?: '—' }}</td>
                                <td style="padding:10px 8px;">
                                    @if($s->status === 'scheduled')
                                        <span style="background:#ecfdf5;color:#065f46;font-size:11px;font-weight:600;padding:2px 9px;border-radius:999px;">{{ __('Scheduled') }}</span>
                                    @else
                                        <span style="background:#f1f5f9;color:#475569;font-size:11px;font-weight:600;padding:2px 9px;border-radius:999px;">{{ __('Cancelled') }}</span>
                                    @endif
                                </td>
                                <td style="padding:10px 8px;">
                                    @if($s->status === 'scheduled')
                                        <form action="{{ route('instructor.temporary-slots.destroy', $s->id) }}" method="POST" onsubmit="return confirm('{{ __('Cancel this temporary slot?') }}');">@csrf @method('DELETE')
                                            <button class="btn-corp-secondary" style="padding:5px 10px;font-size:12px;color:#dc2626;">{{ __('Cancel') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top:14px;">{{ $slots->links() }}</div>
            @else
                <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                    <i class="fas fa-calendar-day" style="font-size:30px;margin-bottom:10px;"></i>
                    <p style="margin:0;">{{ __('No temporary slots yet. Assign one from a student\'s Edit page.') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
