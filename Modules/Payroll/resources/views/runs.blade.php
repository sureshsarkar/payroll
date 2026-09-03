@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">Payroll Runs</h1>
            <p class="s">Prepare monthly payroll — attendance & statutory deductions are pulled automatically.</p>
        </div>
        <div class="pv-actions">
            <a href="{{ route('hr.salary.index') }}" class="pv-btn"><i class="fas fa-money-bill-wave"></i> Salary structures</a>
        </div>
    </div>

    <div class="pv-card">
        <div class="h"><i class="fas fa-play-circle" style="color:var(--pv-green)"></i> Prepare a run</div>
        <div class="b">
            <form method="POST" action="{{ route('hr.payroll.prepare') }}" class="pv-inline">
                @csrf
                <div class="pv-field" style="margin:0"><label class="pv-label">Month</label>
                    <select name="month" class="pv-select">
                        @foreach(range(1,12) as $m)<option value="{{ $m }}" {{ $m==$now->month?'selected':'' }}>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>@endforeach
                    </select></div>
                <div class="pv-field" style="margin:0"><label class="pv-label">Year</label>
                    <input type="number" name="year" value="{{ $now->year }}" class="pv-input" style="width:110px"></div>
                <button class="pv-btn g"><i class="fas fa-cogs"></i> Prepare payroll</button>
            </form>
        </div>
    </div>

    <div class="pv-card">
        <div class="h"><i class="fas fa-list pv-muted"></i> All runs</div>
        <div class="b tight">
            @if($runs->isEmpty())
                <div class="pv-empty"><div class="ic"><i class="fas fa-receipt"></i></div>No payroll runs yet.</div>
            @else
            <div class="pv-tw">
            <table class="pv-table" style="min-width:560px">
                <thead><tr><th>Period</th><th>Status</th><th class="pv-c">Employees</th><th class="pv-r">Total Net</th><th class="pv-r"></th></tr></thead>
                <tbody>
                @foreach($runs as $run)
                    <tr>
                        <td><strong>{{ $run->periodLabel() }}</strong></td>
                        <td><span class="pv-badge {{ $run->status }}">{{ $run->statusLabel() }}</span></td>
                        <td class="pv-c">{{ $run->employee_count }}</td>
                        <td class="pv-r">₹{{ number_format($run->total_net,2) }}</td>
                        <td class="pv-r" style="white-space:nowrap">
                            <a href="{{ route('hr.payroll.show',$run) }}" class="pv-btn sm">Open</a>
                            @if($run->isDeletable())
                                <form method="POST" action="{{ route('hr.payroll.destroy',$run) }}" style="display:inline"
                                      onsubmit="return confirm('Delete the {{ $run->periodLabel() }} payroll run? It is removed from the list and from employees\' payslips, but can be restored from “Deleted runs”.')">
                                    @csrf @method('DELETE')
                                    <button class="pv-btn d sm" title="Delete run"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>

    @if(isset($deleted) && $deleted->isNotEmpty())
    <div class="pv-card">
        <div class="h"><i class="fas fa-trash-can pv-muted"></i> Deleted runs ({{ $deleted->count() }})
            <span class="pv-mut2">Hidden from the list and reports — still in the database</span></div>
        <div class="b tight">
            <div class="pv-tw">
            <table class="pv-table" style="min-width:520px">
                <thead><tr><th>Period</th><th>Status</th><th class="pv-c">Employees</th><th class="pv-r">Total Net</th><th>Deleted</th><th class="pv-r"></th></tr></thead>
                <tbody>
                @foreach($deleted as $run)
                    <tr>
                        <td><strong>{{ $run->periodLabel() }}</strong></td>
                        <td><span class="pv-badge {{ $run->status }}">{{ $run->statusLabel() }}</span></td>
                        <td class="pv-c">{{ $run->employee_count }}</td>
                        <td class="pv-r">₹{{ number_format($run->total_net,2) }}</td>
                        <td class="pv-mut2">{{ optional($run->deleted_at)->diffForHumans() }}</td>
                        <td class="pv-r">
                            <form method="POST" action="{{ route('hr.payroll.restore',$run) }}" style="display:inline">
                                @csrf
                                <button class="pv-btn g sm"><i class="fas fa-rotate-left"></i> Restore</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
