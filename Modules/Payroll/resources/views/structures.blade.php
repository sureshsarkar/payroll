@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">Salary Structures</h1>
            <p class="s">Set each employee's monthly salary. Statutory deductions apply automatically.</p>
        </div>
        <div class="pv-actions">
            <a href="{{ route('hr.payroll.index') }}" class="pv-btn p"><i class="fas fa-receipt"></i> Payroll runs</a>
        </div>
    </div>

    <div class="pv-card">
        <div class="b tight">
            @if($team->isEmpty())
                <div class="pv-empty"><div class="ic"><i class="fas fa-money-check-alt"></i></div>No employees yet. Add them under Employees first.</div>
            @else
            <div class="pv-tw">
            <table class="pv-table" style="min-width:640px">
                <thead><tr><th>Employee</th><th class="pv-r">Current Gross</th><th style="width:46%">Set / update structure</th></tr></thead>
                <tbody>
                @foreach($team as $emp)
                    @php $s = $current->get($emp->id); @endphp
                    <tr>
                        <td><strong>{{ $emp->name }}</strong> <span class="pv-mut2">#{{ $emp->id }}</span></td>
                        <td class="pv-r">{{ $s ? '₹'.number_format($s->gross_monthly,2) : '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('hr.salary.store') }}" class="pv-inline" style="gap:6px">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $emp->id }}">
                                <input name="basic" class="pv-input" style="width:100px" placeholder="Basic" value="{{ $s?->basic() }}">
                                <input name="hra_percent" class="pv-input" style="width:82px" placeholder="HRA %" value="40">
                                <input name="special" class="pv-input" style="width:96px" placeholder="Special" value="0">
                                <button class="pv-btn g sm">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            <p class="pv-mut2" style="margin:12px 4px 0">PF / ESIC / Professional Tax are computed automatically — no need to enter them here.</p>
            @endif
        </div>
    </div>
</div>
@endsection
