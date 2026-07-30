<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Attendance') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/bootstrap.min.css') }}">
    <style>
        body { background:#f4f6fa; }
        .navbar-payroll { background:#1f2d3d; }
        .navbar-payroll a { color:#fff; }
        .stat-card { border:0; border-radius:.6rem; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .stat-card .h3 { font-weight:700; }
        .cal-cell { height:78px; border:1px solid #e6e9ef; border-radius:.4rem; padding:.35rem; font-size:.8rem; }
        .cal-muted { background:#fafbfc; color:#aeb6c2; }
        .badge-Present{background:#1e9e5a;} .badge-Absent{background:#d0403a;}
        .badge-HalfDay{background:#e08a1e;} .badge-Leave{background:#5b6ee1;}
        .badge-Holiday{background:#6c757d;} .badge-WFH{background:#0f9bb0;}
        .status-dot{display:inline-block;width:.6rem;height:.6rem;border-radius:50%;}
    </style>
</head>
<body>
<nav class="navbar navbar-payroll navbar-expand-lg px-3 mb-4">
    <span class="navbar-brand mb-0 h1">🕐 {{ config('app.name') }} Payroll</span>
    <span class="text-white-50 ms-auto small">@yield('subtitle')</span>
</nav>

<div class="container pb-5">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul></div>
    @endif

    @yield('content')
</div>
</body>
</html>
