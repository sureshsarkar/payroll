{{-- 1:1 Instant Meeting launcher — Zoom Component View, coach-branded (white-label).
     Shared by the coach (host) and the invited student (attendee); the role is
     decided server-side and returned by the signature endpoint. --}}
@php
    $brandName = (isset($brand) && is_object($brand) ? ($brand->name ?? null) : null) ?: config('app.name');
    $primary   = (isset($brand) && is_object($brand) ? ($brand->primaryColor ?? null) : null) ?: '#4f46e5';
    $logo      = null;
    try { $logo = (isset($brand) && is_object($brand) && method_exists($brand, 'logoUrl') && ($brand->ownLogo ?? false) && !($brand->isPlatformDefault ?? false)) ? $brand->logoUrl() : null; } catch (\Throwable $e) { $logo = null; }
    $title     = $meeting->topic ?: __('1:1 Meeting');
    $withName  = $isHost ? ($meeting->student?->name ?? '') : ($meeting->coach?->name ?? '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ $title }} — {{ $brandName }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://source.zoom.us/3.8.5/css/bootstrap.css">
    <link rel="stylesheet" href="https://source.zoom.us/3.8.5/css/react-select.css">
    <style>
        :root { --brand-primary: {{ $primary }}; --header-height:60px; }
        * { box-sizing:border-box; }
        html,body { margin:0; padding:0; height:100dvh; width:100vw; overflow:hidden;
            font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#1c1a4a; background:#0b0d12; }
        .shell { display:flex; flex-direction:column; height:100dvh; width:100vw; }
        .hdr { flex:0 0 auto; min-height:var(--header-height); display:flex; align-items:center; justify-content:space-between;
            gap:16px; padding:10px 20px; background:#fff; border-bottom:1px solid #e5e7eb; z-index:10; }
        .hdr .brand { display:flex; align-items:center; gap:14px; min-width:0; }
        .hdr .brand img { height:32px; max-width:140px; object-fit:contain; }
        .hdr .brand-text { font-weight:700; font-size:16px; color:var(--brand-primary); }
        .hdr .ctx { display:flex; flex-direction:column; gap:2px; min-width:0; }
        .hdr .ctx .k { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; font-weight:600; }
        .hdr .ctx .t { font-size:14px; font-weight:600; color:#1c1a4a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .hdr .meta { display:flex; align-items:center; gap:12px; }
        .role { font-size:11px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; padding:4px 10px; border-radius:999px;
            background:rgba(79,70,229,.1); color:var(--brand-primary); }
        .leave { padding:8px 16px; background:#ef4444; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer; }
        .leave:hover { filter:brightness(.92); }
        .stage { flex:1 1 auto; min-height:0; width:100%; display:flex; align-items:center; justify-content:center; padding:16px;
            background:radial-gradient(circle at 20% 0%,rgba(79,70,229,.16),transparent 50%),linear-gradient(180deg,#0f1117,#161922); }
        #meetingSDKElement { position:relative; width:100%; max-width:1100px; height:100%; max-height:720px;
            background:linear-gradient(135deg,#1a1c4a,#0f1230); border-radius:14px; overflow:hidden; box-shadow:0 16px 48px rgba(0,0,0,.45); }
        #splash { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding:24px;
            background:radial-gradient(ellipse at top,rgba(79,70,229,.18),transparent 60%),linear-gradient(180deg,#f8fafc,#fff); z-index:5; }
        .card { width:100%; max-width:460px; background:#fff; border:1px solid #e5e7eb; border-radius:16px;
            box-shadow:0 12px 40px rgba(28,26,74,.08); padding:28px 24px; text-align:center; }
        .spin { width:44px; height:44px; border:3px solid #e5e7eb; border-top-color:var(--brand-primary); border-radius:50%;
            animation:sp .9s linear infinite; margin:0 auto 14px; }
        @keyframes sp { to { transform:rotate(360deg); } }
        .st { font-size:14px; color:#4b5563; min-height:20px; }
        #splash.error .spin { display:none; }
        #splash.error .st { color:#b91c1c; font-weight:500; }
        .acts { display:flex; gap:8px; justify-content:center; margin-top:18px; flex-wrap:wrap; }
        .btn { padding:10px 20px; border-radius:10px; font-weight:600; font-size:14px; text-decoration:none; border:none; cursor:pointer; }
        .btn-p { background:var(--brand-primary); color:#fff; } .btn-s { background:#f3f4f6; color:#1c1a4a; }
        #splash:not(.error) .acts { display:none; }
        .wait { font-size:14px; color:#374151; max-width:420px; line-height:1.5; }
        @media (max-width:639px){ #meetingSDKElement { max-height:100%; border-radius:0; } .stage { padding:0; } .role { display:none; } }
    </style>
</head>
<body>
<div class="shell">
    <header class="hdr">
        <div class="brand">
            @if($logo)<img src="{{ $logo }}" alt="{{ $brandName }}">@else<span class="brand-text">{{ $brandName }}</span>@endif
            <div class="ctx">
                <div class="k">{{ __('1:1 Meeting') }}@if($withName) · {{ $withName }}@endif</div>
                <div class="t">{{ $title }}</div>
            </div>
        </div>
        <div class="meta">
            <span class="role">{{ $isHost ? __('Host') : __('Attendee') }}</span>
            <button type="button" class="leave" id="leaveBtn">{{ __('Leave') }}</button>
        </div>
    </header>
    <section class="stage">
        <main id="meetingSDKElement">
            <div id="splash">
                <div class="card">
                    <div class="spin"></div>
                    <div id="st" class="st">{{ __('Connecting to your meeting…') }}</div>
                    <div class="acts">
                        <button type="button" class="btn btn-s" onclick="window.location.reload()">{{ __('Retry') }}</button>
                    </div>
                </div>
            </div>
        </main>
    </section>
</div>

<script src="https://source.zoom.us/3.8.5/lib/vendor/react.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/react-dom.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/redux.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/redux-thunk.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/lodash.min.js"></script>
<script src="https://source.zoom.us/zoom-meeting-embedded-3.8.5.min.js"></script>
<script>
(function(){
    "use strict";
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var isHost = @json((bool) $isHost);
    var statusUrl = @json(route('instant-meeting.status', $meeting->id));
    var signUrl   = @json(route('instant-meeting.signature', $meeting->id));
    var attendUrl = @json(route('instant-meeting.attendance', $meeting->id));
    var endUrl    = @json($isHost ? route('instructor.instant-meetings.end', $meeting->id) : null);
    var leaveUrl  = @json(url('/'));

    var splash = document.getElementById('splash');
    var stEl   = document.getElementById('st');
    var attendanceLogged = false, joined = false, joinFlowStarted = false, pollTimer = null;

    function setStatus(m){ if(stEl) stEl.textContent = m; }
    function showError(m){ if(splash){ splash.classList.add('error'); } setStatus(m); }
    function hideSplash(){ var e=document.getElementById('splash'); if(e&&e.parentNode) e.parentNode.removeChild(e); }

    function postAttendance(evt, beacon){
        if(beacon && navigator.sendBeacon){
            navigator.sendBeacon(attendUrl, new Blob(['_token='+encodeURIComponent(csrf)+'&event='+evt], {type:'application/x-www-form-urlencoded'}));
            return;
        }
        fetch(attendUrl, {method:'POST',credentials:'same-origin',
            headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({event:evt}), keepalive:true}).catch(function(){});
    }

    document.getElementById('leaveBtn').addEventListener('click', function(){
        function go(){
            if(isHost && endUrl){
                fetch(endUrl, {method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}})
                    .then(function(){ window.location = leaveUrl; }).catch(function(){ window.location = leaveUrl; });
            } else { window.location = leaveUrl; }
        }
        try {
            if(window.__z && typeof window.__z.leaveMeeting==='function'){ window.__z.leaveMeeting().then(go).catch(go); return; }
        } catch(e){}
        go();
    });

    // Poll status: student waits for the coach; host can join immediately.
    function poll(){
        fetch(statusUrl, {credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ if(r.status===403){ showError('{{ __('You do not have access to this meeting.') }}'); return null; } return r.ok? r.json():null; })
        .then(function(d){
            if(!d || !d.ok) return;
            if(d.status !== 'active'){ clearInterval(pollTimer); showError('{{ __('This meeting has ended.') }}'); return; }
            if(d.can_join){ startJoin(); }
            else { setStatus('{{ __('Waiting for your coach to start the meeting…') }}'); }
        }).catch(function(){});
    }
    setStatus('{{ __('Checking meeting status…') }}');
    poll(); pollTimer = setInterval(poll, 6000);

    function startJoin(){
        if(joinFlowStarted) return; joinFlowStarted = true; clearInterval(pollTimer);
        if(!window.isSecureContext || !navigator.mediaDevices){
            showError('{{ __('Video meetings require a secure (HTTPS) connection. Please open this site over https://') }}'); return;
        }
        setStatus('{{ __('Authenticating…') }}');
        fetch(signUrl, {method:'POST',credentials:'same-origin',
            headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}})
        .then(function(res){
            if(res.status===425){ joinFlowStarted=false; if(!pollTimer) pollTimer=setInterval(poll,6000); return null; }
            if(!res.ok){ throw new Error('HTTP '+res.status); }
            return res.json();
        })
        .then(function(cfg){
            if(!cfg) return;
            leaveUrl = cfg.leaveUrl || leaveUrl;
            setStatus('{{ __('Loading meeting…') }}');
            var client = ZoomMtgEmbedded.createClient(); window.__z = client;
            var root = document.getElementById('meetingSDKElement');
            var deadline = setTimeout(function(){ if(!joined) showError('{{ __('Could not reach Zoom. Please Retry.') }}'); }, 20000);

            window.addEventListener('pagehide', function(){
                if(joined && attendanceLogged) postAttendance('leave', true);
                try { if(window.__z && window.__z.leaveMeeting) window.__z.leaveMeeting(); } catch(e){}
            });

            client.init({ zoomAppRoot:root, language:'en-US', customize:{ video:{}, toolbar:{}, chat:{}, meeting:{}, meetingInfo:[], video_share:{}, settings:{} } })
            .then(function(){
                try { client.on('connection-change', function(p){
                    if(p && p.state==='Connected'){ joined=true; clearTimeout(deadline); hideSplash();
                        if(!attendanceLogged){ attendanceLogged=true; postAttendance('join'); } }
                    if(p && (p.state==='Fail'||p.state==='Closed')){ if(joined){ postAttendance('leave'); } }
                }); } catch(e){}
                setStatus('{{ __('Joining…') }}');
                var payload = { signature:cfg.signature, sdkKey:cfg.sdkKey, meetingNumber:cfg.meetingNumber, userName:cfg.userName, userEmail:cfg.userEmail };
                if(cfg.password) payload.password = cfg.password;
                if(cfg.zak) payload.zak = cfg.zak;
                return client.join(payload);
            })
            .then(function(){ joined=true; clearTimeout(deadline); hideSplash(); })
            .catch(function(err){ clearTimeout(deadline); showError('{{ __('Failed to start meeting:') }} ' + (err && (err.reason||err.message) || 'error')); });
        })
        .catch(function(err){ showError(err && err.message ? err.message : '{{ __('Could not start the meeting.') }}'); });
    }
})();
</script>
</body>
</html>
