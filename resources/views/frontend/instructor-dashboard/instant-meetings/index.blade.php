@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    .im { --im-primary:#4f46e5; --im-ink:#1c1a4a; --im-muted:#64748b; --im-line:#e6e8f0; }
    .im * { box-sizing:border-box; }
    .im-head h4 { font-size:20px; font-weight:750; color:var(--im-ink); margin:0; display:flex; align-items:center; gap:9px; }
    .im-head p { color:var(--im-muted); font-size:13.5px; margin:6px 0 18px; }
    .im-card { background:#fff; border:1px solid var(--im-line); border-radius:14px; box-shadow:0 1px 2px rgba(16,24,40,.04); }
    .im-card__b { padding:20px 22px; }
    .im-alert { border-radius:10px; padding:11px 14px; font-size:13.5px; margin-bottom:16px; }
    .im-alert.warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .im-alert.err { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; }
    .im-active { display:flex; align-items:center; gap:14px; flex-wrap:wrap; background:linear-gradient(180deg,#ecfdf5,#f8faff);
                 border:1px solid #a7f3d0; border-radius:13px; padding:14px 18px; margin-bottom:18px; }
    .im-active .dot { width:9px; height:9px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,.18); }
    .im-active b { color:var(--im-ink); }
    .im-active .sp { margin-left:auto; display:flex; gap:8px; }
    .im-field { margin-bottom:15px; }
    .im-field label { display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
    .im-input { width:100%; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; font-size:14px; color:#0f172a; background:#fff; font-family:inherit; }
    .im-input:focus { outline:none; border-color:var(--im-primary); box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    .im-row { display:flex; gap:14px; } .im-row > * { flex:1; }
    .im-start { background:var(--im-primary); color:#fff; border:none; border-radius:11px; padding:12px 24px; font-weight:700; font-size:14.5px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
    .im-start:hover { opacity:.93; } .im-start[disabled] { opacity:.6; cursor:not-allowed; }
    .im-btn-sm { border:1px solid var(--im-line); background:#fff; color:var(--im-ink); border-radius:9px; padding:7px 14px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; }
    .im-btn-sm.rejoin { background:var(--im-primary); color:#fff; border-color:var(--im-primary); }
    .im-btn-sm.end { color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
    .im-sec { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin:0 0 12px; }
    table.im-t { width:100%; border-collapse:collapse; font-size:13px; }
    table.im-t th { text-align:left; font-weight:600; color:#94a3b8; font-size:11.5px; letter-spacing:.04em; text-transform:uppercase; padding:9px 12px; border-bottom:1px solid var(--im-line); }
    table.im-t td { padding:10px 12px; border-bottom:1px solid #f1f3f9; }
    .im-pill { font-size:11px; font-weight:700; border-radius:100px; padding:2px 10px; text-transform:capitalize; }
    .im-pill.active { background:#dcfce7; color:#166534; } .im-pill.ended { background:#e2e8f0; color:#475569; } .im-pill.cancelled { background:#fee2e2; color:#991b1b; }
    @media (max-width:640px){ .im-row { flex-direction:column; } }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .im { --im-ink:#e2e8f0; --im-muted:#94a3b8; --im-line:#2a3a55; }
    html[data-theme="dark"] .im-card  { background:#1e293b; box-shadow:none; }
    html[data-theme="dark"] .im-input { background:#1e293b; color:#e2e8f0; border-color:#2a3a55; }
    html[data-theme="dark"] .im-btn-sm { background:#1e293b; }
    html[data-theme="dark"] .im-pill.ended { background:#22304a; color:#94a3b8; }
</style>

<div class="im">
    <div class="im-head">
        <h4><i class="fas fa-video"></i> {{ __('Instant Meeting 1:1') }}</h4>
        <p>{{ __('Start a private 1:1 video meeting with one of your students — for consultation, doubt-solving or personal training. Separate from your batch live classes.') }}</p>
    </div>

    @if(!$zoomConfigured)
        <div class="im-alert warn">
            {{ __('Connect your Zoom account first to start meetings.') }}
            <a href="{{ route('instructor.zoom-setting.index') }}" style="font-weight:700;color:#92400e;">{{ __('Open Zoom Settings →') }}</a>
        </div>
    @endif

    @if($active)
        <div class="im-active">
            <span class="dot"></span>
            <div><b>{{ __('Meeting in progress') }}</b> {{ __('with') }} {{ $active->student?->name }} — <span style="text-transform:capitalize;color:#64748b;">{{ $active->purpose }}</span></div>
            <div class="sp">
                <a href="{{ route('instant-meeting.room', $active->id) }}" class="im-btn-sm rejoin">{{ __('Rejoin') }}</a>
                <button type="button" class="im-btn-sm end" data-end="{{ $active->id }}">{{ __('End meeting') }}</button>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7 mb-3">
            <div class="im-card"><div class="im-card__b">
                <div class="im-sec">{{ __('Start a new 1:1 meeting') }}</div>
                <div class="im-alert err" data-err hidden></div>

                <form data-start-form>
                    @csrf
                    <div class="im-field">
                        <label>{{ __('Student') }} *</label>
                        <select name="student_id" class="im-input" required>
                            <option value="">{{ __('Choose a student…') }}</option>
                            @foreach($students as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>
                            @endforeach
                        </select>
                        @if($students->isEmpty())
                            <small style="color:#94a3b8;">{{ __('No students in your roster yet.') }}</small>
                        @endif
                    </div>
                    <div class="im-row">
                        <div class="im-field">
                            <label>{{ __('Purpose') }}</label>
                            <select name="purpose" class="im-input">
                                <option value="general">{{ __('General') }}</option>
                                <option value="consultation">{{ __('Consultation') }}</option>
                                <option value="doubt">{{ __('Doubt solving') }}</option>
                                <option value="training">{{ __('Personal training') }}</option>
                            </select>
                        </div>
                        <div class="im-field">
                            <label>{{ __('Duration (minutes)') }}</label>
                            <input type="number" name="duration" class="im-input" value="30" min="5" max="240">
                        </div>
                    </div>
                    <div class="im-field">
                        <label>{{ __('Topic (optional)') }}</label>
                        <input type="text" name="topic" class="im-input" maxlength="190" placeholder="{{ __('e.g. Posture review & breathing') }}">
                    </div>
                    <button type="submit" class="im-start" data-start {{ (!$zoomConfigured || $students->isEmpty()) ? 'disabled' : '' }}>
                        <i class="fas fa-play"></i> <span data-start-label>{{ __('Start Now') }}</span>
                    </button>
                    <p style="font-size:12px;color:#94a3b8;margin:10px 0 0;">{{ __('Your student is notified instantly (in-app + email) with a join link.') }}</p>
                </form>
            </div></div>
        </div>

        <div class="col-lg-5 mb-3">
            <div class="im-card"><div class="im-card__b">
                <div class="im-sec">{{ __('Recent meetings') }}</div>
                @if($recent->count())
                    <div style="overflow-x:auto;">
                        <table class="im-t">
                            <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Status') }}</th><th>{{ __('When') }}</th></tr></thead>
                            <tbody>
                                @foreach($recent as $m)
                                    <tr>
                                        <td>{{ $m->student?->name ?? '—' }}</td>
                                        <td style="text-transform:capitalize;color:#64748b;">{{ $m->purpose }}</td>
                                        <td><span class="im-pill {{ $m->status }}">{{ $m->status }}</span></td>
                                        <td style="color:#64748b;white-space:nowrap;">{{ $m->started_at?->format('d M, H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="font-size:13px;color:#94a3b8;padding:14px 0;">{{ __('No meetings yet.') }}</div>
                @endif
            </div></div>
        </div>
    </div>
</div>

<script>
(function(){
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
             || document.querySelector('input[name="_token"]')?.value || '';
    var form = document.querySelector('[data-start-form]');
    var errB = document.querySelector('[data-err]');
    if (form) {
        var btn = form.querySelector('[data-start]');
        var label = form.querySelector('[data-start-label]');
        form.addEventListener('submit', function(e){
            e.preventDefault();
            errB.hidden = true;
            btn.disabled = true; var orig = label.textContent; label.textContent = '{{ __('Starting…') }}';
            fetch('{{ route('instructor.instant-meetings.start') }}', {
                method:'POST',
                headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
                body:new FormData(form)
            }).then(function(r){ return r.json().then(function(j){ return {s:r.status,j:j}; }); })
            .then(function(res){
                if(res.j && res.j.ok && res.j.redirect_url){ window.location = res.j.redirect_url; return; }
                errB.hidden=false; errB.textContent = (res.j && res.j.message) || '{{ __('Could not start the meeting.') }}';
                btn.disabled=false; label.textContent=orig;
            }).catch(function(){
                errB.hidden=false; errB.textContent='{{ __('Network error. Please try again.') }}';
                btn.disabled=false; label.textContent=orig;
            });
        });
    }
    document.querySelectorAll('[data-end]').forEach(function(b){
        b.addEventListener('click', function(){
            if(!confirm('{{ __('End this meeting?') }}')) return;
            fetch('{{ url('instructor/instant-meetings') }}/'+b.dataset.end+'/end', {
                method:'POST', headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
            }).then(function(){ window.location.reload(); });
        });
    });
})();
</script>
@endsection
