<!doctype html>
<html lang="en">
<head><meta charset="utf-8">
@include('payroll::partials._wage-register-styles')
</head>
<body>
@include('payroll::partials._wage-register-sheet', [
    'run'           => $run,
    'rows'          => $rows,
    'totals'        => $totals,
    'establishment' => $establishment,
])
</body></html>
