@php $ok = (bool) $credential; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Verify Certificate') }} · {{ $uid }}</title>
    <style>
        :root{ --ink:#0f172a; --muted:#64748b; --line:#e6ebe9; --ok:#0f766e; --ok-bg:#ecfdf5; --bad:#b91c1c; --bad-bg:#fef2f2; }
        *{box-sizing:border-box} html,body{height:100%}
        body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);
            background:radial-gradient(1000px 500px at 50% -10%, #e8f5f1, transparent 60%),#f4f7f6;
            display:flex;align-items:center;justify-content:center;padding:24px;}
        .card{width:100%;max-width:520px;background:#fff;border:1px solid var(--line);border-radius:18px;
            box-shadow:0 1px 2px rgba(0,0,0,.04),0 20px 50px rgba(15,23,42,.08);overflow:hidden}
        .top{padding:26px 28px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:13px}
        .badge{width:46px;height:46px;border-radius:50%;display:grid;place-items:center;flex:0 0 auto}
        .badge.ok{background:var(--ok-bg);color:var(--ok)} .badge.bad{background:var(--bad-bg);color:var(--bad)}
        .top h1{margin:0;font-size:17px;letter-spacing:-.01em}
        .top p{margin:3px 0 0;font-size:13px;color:var(--muted)}
        .body{padding:24px 28px}
        .row{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid #f1f5f4}
        .row:last-child{border-bottom:0}
        .k{font-size:12.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600}
        .v{font-size:14.5px;font-weight:600;text-align:right}
        .uid{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px}
        .foot{padding:16px 28px;background:#fafbfb;border-top:1px solid var(--line);font-size:12px;color:var(--muted);text-align:center}
        .miss{padding:8px 0 4px;color:var(--muted);font-size:14px;line-height:1.6}
        .share{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
        .btn{flex:1 1 auto;display:inline-flex;align-items:center;justify-content:center;gap:8px;
            border-radius:11px;padding:11px 14px;font-size:13.5px;font-weight:600;cursor:pointer;
            text-decoration:none;border:1px solid transparent;transition:filter .15s,background .15s,border-color .15s;white-space:nowrap}
        .btn--li{background:#0a66c2;color:#fff}
        .btn--li:hover{filter:brightness(1.07)}
        .btn--ghost{background:#fff;color:var(--ink);border-color:var(--line)}
        .btn--ghost:hover{background:#f5f8f7;border-color:#cbd5e1}
        .btn svg{flex:0 0 auto}
        .btn--ok{color:var(--ok);border-color:var(--ok)}
    </style>
</head>
<body>
    <div class="card">
        <div class="top">
            @if($ok)
                <span class="badge ok">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <div><h1>{{ __('Certificate verified') }}</h1><p>{{ __('This is a genuine credential issued through the platform.') }}</p></div>
            @else
                <span class="badge bad">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                </span>
                <div><h1>{{ __('Certificate not found') }}</h1><p>{{ __('No credential matches this code.') }}</p></div>
            @endif
        </div>

        <div class="body">
            @if($ok)
                <div class="row"><span class="k">{{ __('Awarded to') }}</span><span class="v">{{ $credential->student_name ?: '—' }}</span></div>
                <div class="row"><span class="k">{{ __('Course') }}</span><span class="v">{{ $credential->course_title ?: '—' }}</span></div>
                @if($credential->coach_name)
                    <div class="row"><span class="k">{{ __('Issued by') }}</span><span class="v">{{ $credential->coach_name }}</span></div>
                @endif
                <div class="row"><span class="k">{{ __('Issued on') }}</span><span class="v">{{ optional($credential->issued_on)->format('d M Y') ?: '—' }}</span></div>
                <div class="row"><span class="k">{{ __('Credential ID') }}</span><span class="v uid">{{ $credential->uid }}</span></div>

                @php
                    // LinkedIn "Add to Profile" — prefills the certification section.
                    $certUrl = url()->current();
                    $issuedOn = $credential->issued_on;
                    $liOrg   = $credential->coach_name ?: config('app.name');
                    $liName  = $credential->course_title ? __('Certificate of Completion — :c', ['c' => $credential->course_title]) : __('Certificate of Completion');
                    $liQuery = http_build_query(array_filter([
                        'startTask'        => 'CERTIFICATION_NAME',
                        'name'             => $liName,
                        'organizationName' => $liOrg,
                        'issueYear'        => optional($issuedOn)->format('Y'),
                        'issueMonth'       => optional($issuedOn)->format('n'),
                        'certUrl'          => $certUrl,
                        'certId'           => $credential->uid,
                    ]));
                @endphp
                <div class="share">
                    <a class="btn btn--li" href="https://www.linkedin.com/profile/add?{{ $liQuery }}" target="_blank" rel="noopener">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z"/></svg>
                        {{ __('Add to LinkedIn') }}
                    </a>
                    <button type="button" class="btn btn--ghost" id="cvCopy" data-copy="{{ $certUrl }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                        <span id="cvCopyTxt">{{ __('Copy link') }}</span>
                    </button>
                </div>
            @else
                <p class="miss">{{ __('The code') }} <b class="uid">{{ $uid }}</b> {{ __('did not match any issued certificate. Please check the ID or scan the QR again.') }}</p>
            @endif
        </div>

        <div class="foot">{{ __('Certificate verification') }} · {{ config('app.name') }}</div>
    </div>

    @if($ok)
    <script>
        (function () {
            var btn = document.getElementById('cvCopy');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-copy'), txt = document.getElementById('cvCopyTxt');
                var done = function () {
                    btn.classList.add('btn--ok');
                    if (txt) txt.textContent = @json(__('Copied!'));
                    setTimeout(function () { btn.classList.remove('btn--ok'); if (txt) txt.textContent = @json(__('Copy link')); }, 1800);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, function () {});
                } else {
                    var t = document.createElement('textarea'); t.value = url; document.body.appendChild(t);
                    t.select(); try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(t);
                }
            });
        })();
    </script>
    @endif
</body>
</html>
