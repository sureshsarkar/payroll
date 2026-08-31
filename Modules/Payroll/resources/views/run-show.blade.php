@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">{{ $run->periodLabel() }} <span class="pv-badge {{ $run->status }}" style="font-size:12px;vertical-align:middle">{{ str_replace('_',' ',$run->status) }}</span></h1>
            <p class="s">Payroll run detail</p>
        </div>
        <div class="pv-actions">
            <a href="{{ route('hr.payroll.payslips', $run) }}" class="pv-btn sm d" {{ $items->isEmpty()?'hidden':'' }}>
                <i class="fas fa-file-pdf"></i> All payslips
            </a>
            <span class="pv-btngrp">
                <a href="{{ route('hr.payroll.export', ['run'=>$run,'format'=>'xlsx']) }}" class="pv-btn sm g">Excel</a>
                <a href="{{ route('hr.payroll.export', ['run'=>$run,'format'=>'pdf']) }}" class="pv-btn sm d">PDF</a>
                <a href="{{ route('hr.payroll.export', ['run'=>$run,'format'=>'csv']) }}" class="pv-btn sm">CSV</a>
            </span>
            @if($run->status === 'draft')
                <form method="POST" action="{{ route('hr.payroll.submit',$run) }}" style="display:inline">@csrf
                    <button class="pv-btn p" {{ $items->isEmpty()?'disabled':'' }}><i class="fas fa-paper-plane"></i> Submit for approval</button></form>
            @elseif($run->status === 'hr_submitted')
                <span class="pv-muted" style="font-size:13px">Awaiting Super Admin approval</span>
                <form method="POST" action="{{ route('hr.payroll.reopen',$run) }}" style="display:inline" onsubmit="return confirm('Send this run back to draft? You will need to prepare it again before resubmitting.')">@csrf
                    <button class="pv-btn sm"><i class="fas fa-undo"></i> Reopen for correction</button></form>
            @else
                <span style="color:var(--pv-green);font-size:13px;font-weight:600"><i class="fas fa-check-circle"></i> Approved</span>
            @endif
            <a href="{{ route('hr.payroll.index') }}" class="pv-btn sm">‹ Runs</a>
        </div>
    </div>

    @if($staleIds->isNotEmpty())
        <div class="pv-card" style="border-color:var(--pv-amber);background:#fffbeb">
            <div class="b" style="display:flex;align-items:center;gap:8px;color:#92400e">
                <i class="fas fa-triangle-exclamation"></i>
                <span>{{ $staleIds->count() }} employee(s) below have attendance or leave changes recorded
                    <strong>after</strong> this run was last computed — their net pay may be out of date.
                    @if($run->status === 'draft')
                        Prepare the run again to pick up the change.
                    @else
                        Reopen the run for correction to pick up the change.
                    @endif
                </span>
            </div>
        </div>
    @endif

    <div class="pv-card">
        <div class="b tight">
            @if($items->isEmpty())
                <div class="pv-empty">No items. Go back and prepare the run.</div>
            @else
            <div class="pv-tw">
            <table class="pv-table" style="min-width:680px">
                <thead><tr><th>Employee</th><th class="pv-c">Payable</th><th class="pv-c">LOP</th><th class="pv-r">Gross</th><th class="pv-r">Deductions</th><th class="pv-r">Net Pay</th><th class="pv-c">Slip</th></tr></thead>
                <tbody>
                @foreach($items as $it)
                    <tr>
                        <td><strong>{{ $it->employee->name ?? 'Employee #'.$it->user_id }}</strong>
                            @if($staleIds->contains($it->id))
                                <span class="pv-badge" style="background:#fef3c7;color:#92400e" title="Attendance/leave changed after this was computed"><i class="fas fa-triangle-exclamation"></i> stale</span>
                            @endif
                        </td>
                        <td class="pv-c">{{ $it->payable_days }}</td>
                        <td class="pv-c" style="color:var(--pv-red)">{{ $it->lop_days }}</td>
                        <td class="pv-r">₹{{ number_format($it->total_earnings,2) }}</td>
                        <td class="pv-r">₹{{ number_format($it->total_deductions,2) }}</td>
                        <td class="pv-r" style="font-weight:700">₹{{ number_format($it->net_pay,2) }}</td>
                        <td class="pv-c">
                            <a href="{{ route('hr.payroll.payslip', ['run'=>$run, 'employee'=>$it->user_id]) }}" class="pv-btn sm">Payslip</a>
                            <a href="{{ route('hr.payroll.slip', ['run'=>$run, 'employee'=>$it->user_id]) }}" class="pv-btn sm d">Form IV</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="6" class="pv-r">Total Net</td><td class="pv-r">₹{{ number_format($run->total_net,2) }}</td></tr></tfoot>
            </table>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
