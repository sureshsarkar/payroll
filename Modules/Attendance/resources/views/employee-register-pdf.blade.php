<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    /* Top margin leaves room for the fixed company header that repeats on
       every page; bottom margin for the fixed codes footer. */
    @page { margin: 21mm 5mm 9mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11px; margin: 0; }

    .pagehdr { position: fixed; top: -19mm; left: 0; right: 0; }
    .company { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; }
    .co-addr { text-align: center; font-size: 9px; color: #333; margin-top: 1px; }
    .title { text-align: center; font-size: 11px; font-weight: bold; margin: 2px 0 1px; }
    .remarks { font-size: 8px; color: #333; }

    .pagefoot { position: fixed; bottom: -7mm; left: 0; right: 0; font-size: 8px; color: #444; }

    /* One employee = one block that is never split across a page. */
    .emp-block { page-break-inside: avoid; margin-bottom: 4px; }

    .meta { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
    .meta td { padding: 1px 4px; border: 1px solid #444; font-size: 9px; }
    .meta .label { font-weight: bold; color: #333; }

    .register { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .register th, .register td { border: 1px solid #444; text-align: center; padding: 1px; vertical-align: top; }
    .register th { background: #efefef; font-size: 9.5px; font-weight: normal; line-height: 10.5px; }
    .register .day { width: 2.5%; }
    .register .summary { width: 14%; }
    .entry { min-height: 20px; line-height: 10px; font-size: 9px; }
    .entry .status { font-weight: bold; }
    .register td.hd-cell { background: #eef4ff; }

    .summary-box { text-align: left; font-size: 9px; line-height: 11px; padding: 1px 2px; }
</style>
</head>
<body>
    @php($firstSheet = $sheets[0] ?? null)
    <div class="pagehdr">
        <div class="company">{{ $company['name'] }}</div>
        @if(filled($company['address']))
            <div class="co-addr">{{ $company['address'] }}</div>
        @endif
        @if($firstSheet)
            <div class="title">Attendance Register for the Month {{ $firstSheet['first']->format('F, Y') }}</div>
        @endif
        <div class="remarks">Remarks :- 1. In &nbsp; 2. Out &nbsp; 3. Status</div>
    </div>

    <div class="pagefoot">Codes: PP Present all day, AP 1st half off, PA 2nd half off, AA Absent all day, H Half day, L Half-day leave, EL Earned leave, CL Casual leave, SL Sick leave, OD On duty, WO Weekly off, HD Holiday.</div>

    @foreach($sheets as $sheet)
        @include('attendance::partials._register-body', $sheet)
    @endforeach
</body>
</html>
