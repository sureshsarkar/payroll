{{-- Reusable "Manage batch assignment" modal (2026-07-15). Include ONCE on any
     coach-panel page that has a `.ms-batch-btn` trigger (student list + edit
     student). Self-contained: fetches the student's batch context by AJAX and
     posts the reassign. @once-guarded so two includes render one modal. --}}
@once
<div id="msBatchModal" style="display:none; position:fixed; inset:0; z-index:9999; align-items:center; justify-content:center; padding:18px;">
    <div data-ms-close style="position:absolute; inset:0; background:rgba(15,23,42,.55);"></div>
    <div style="position:relative; width:100%; max-width:480px; max-height:92vh; overflow:auto; background:#fff; border-radius:16px; padding:22px; box-shadow:0 24px 60px -12px rgba(15,23,42,.4);">
        <button type="button" data-ms-close style="position:absolute; top:12px; right:14px; border:none; background:transparent; font-size:24px; color:#94a3b8; cursor:pointer;">&times;</button>
        <div style="font-size:17px; font-weight:600; color:#1e293b;">{{ __('Manage batch assignment') }}</div>
        <div id="msmStudent" style="font-size:13px; color:#64748b; margin-bottom:14px;"></div>
        <div id="msmBody" style="font-size:13.5px; color:#334155;"></div>
        <div id="msmMsg" style="margin-top:12px; font-size:13px;"></div>
    </div>
</div>

<style nonce="{{ csp_nonce() }}">
    #msBatchModal .msm-lbl{display:block; font-size:12px; color:#475569; margin:10px 0 3px;}
    #msBatchModal .msm-sel, #msBatchModal .msm-inp{width:100%; box-sizing:border-box; border:1px solid #e2e8f0; border-radius:9px; padding:9px 11px; font-size:13.5px; background:#fff;}
    #msBatchModal .msm-cur{background:#f1f5f9; border-radius:9px; padding:9px 11px; margin-bottom:6px;}
    #msBatchModal .msm-mode{display:flex; gap:8px; margin:10px 0;}
    #msBatchModal .msm-mode button{flex:1; border:1px solid #e2e8f0; background:#fff; border-radius:9px; padding:8px; font-size:12.5px; cursor:pointer;}
    #msBatchModal .msm-mode button.on{border-color:var(--corp-brand,#4f46e5); background:#eef2ff; color:var(--corp-brand,#4f46e5); font-weight:600;}
    #msBatchModal .msm-meta{background:#f8fafc; border-radius:9px; padding:9px 12px; margin-top:6px; font-size:12px; color:#64748b; display:flex; flex-direction:column; gap:6px;}
    #msBatchModal .msm-meta .row{display:flex; justify-content:space-between; align-items:center; gap:12px;}
    #msBatchModal .msm-meta .row span:first-child{color:#94a3b8;}
    #msBatchModal .msm-meta .row span:last-child{color:#334155; font-weight:500; text-align:right;}
    #msBatchModal .msm-warn{background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:9px; padding:9px 11px; font-size:11.5px; margin-top:10px;}
    #msBatchModal .msm-go{width:100%; margin-top:14px; border:none; border-radius:10px; padding:11px; font-size:14px; font-weight:600; color:#fff; background:var(--corp-brand,#4f46e5); cursor:pointer;}
    #msBatchModal .msm-go:disabled{opacity:.6; cursor:not-allowed;}
    .msm-ok{background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; border-radius:9px; padding:9px 11px;}
    .msm-err{background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:9px; padding:9px 11px;}
</style>

<script nonce="{{ csp_nonce() }}">
(function(){
    if (window.__msBatchInit) return; window.__msBatchInit = true;
    var CSRF = (document.querySelector('meta[name="csrf-token"]')||{}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
    var ctxUrl = "{{ url('instructor/coach-students') }}";
    function post(url, obj){
        var fd = new FormData(); fd.set('_token', CSRF);
        Object.keys(obj).forEach(function(k){ fd.set(k, obj[k]); });
        return fetch(url, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, credentials:'same-origin'})
            .then(function(r){ return r.json().then(function(j){ return {status:r.status, json:j}; }).catch(function(){ return {status:r.status, json:{}}; }); });
    }
    var modal = document.getElementById('msBatchModal'), body = document.getElementById('msmBody'),
        who = document.getElementById('msmStudent'), msg = document.getElementById('msmMsg'), curStudent = null, ctx = null;
    function open(){ modal.style.display='flex'; document.body.style.overflow='hidden'; }
    function close(){ modal.style.display='none'; document.body.style.overflow=''; msg.innerHTML=''; body.innerHTML=''; }
    modal.querySelectorAll('[data-ms-close]').forEach(function(b){ b.addEventListener('click', close); });

    document.addEventListener('click', function(e){
        var b = e.target.closest('.ms-batch-btn'); if(!b) return;
        curStudent = {id:b.getAttribute('data-student-id'), name:b.getAttribute('data-student-name')};
        who.textContent = curStudent.name; body.innerHTML = "{{ __('Loading…') }}"; msg.innerHTML=''; open();
        fetch(ctxUrl + '/' + curStudent.id + '/batch/context', {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, credentials:'same-origin'})
            .then(function(r){ return r.json(); }).then(function(d){ ctx = d; render(); })
            .catch(function(){ body.innerHTML = '<div class="msm-err">' + "{{ __('Could not load batches.') }}" + '</div>'; });
    });

    function render(){
        if(!ctx || !ctx.courses || !ctx.courses.length){ body.innerHTML = '<div class="msm-warn">' + "{{ __('This student is not enrolled in any of your courses, so there is no batch to manage.') }}" + '</div>'; return; }
        var courseSel = ctx.courses.length > 1 ? ('<label class="msm-lbl">{{ __('Course') }}</label><select class="msm-sel" id="msmCourse">' + ctx.courses.map(function(c,i){ return '<option value="'+i+'">'+esc(c.course_title)+'</option>'; }).join('') + '</select>') : '';
        body.innerHTML = courseSel + '<div id="msmCoursePane"></div>';
        var csel = document.getElementById('msmCourse');
        if(csel) csel.addEventListener('change', function(){ pane(+this.value); });
        pane(0);
    }
    function pane(i){
        var c = ctx.courses[i]; var pane = document.getElementById('msmCoursePane');
        var cur = c.current.length ? c.current.map(function(b){ return esc(b.title); }).join(', ') : "{{ __('No batch yet') }}";
        var canMove = c.current.length > 0;
        var opts = c.eligible.map(function(b){ return '<option value="'+b.id+'">'+esc(b.title)+'</option>'; }).join('');
        pane.innerHTML =
            '<div class="msm-cur"><div style="font-size:11px;color:#94a3b8;">{{ __('Current batch') }}</div><div style="font-weight:600;">'+cur+'</div></div>'
            + '<div class="msm-mode"><button type="button" data-mode="move" class="'+(canMove?'on':'')+'" '+(canMove?'':'disabled style="opacity:.5"')+'>'+"{{ __('Move to another batch') }}"+'</button>'
            + '<button type="button" data-mode="add" class="'+(canMove?'':'on')+'">'+"{{ __('Add an extra batch') }}"+'</button></div>'
            + (c.eligible.length ? ('<label class="msm-lbl">{{ __('New batch (same course)') }} *</label><select class="msm-sel" id="msmBatch">'+opts+'</select><div class="msm-meta" id="msmMeta"></div>'
            + '<label class="msm-lbl" id="msmReasonLbl">{{ __('Reason') }} *</label><input class="msm-inp" id="msmReason" placeholder="'+"{{ __('e.g. timing clash') }}"+'">'
            + '<div class="msm-warn" id="msmWarn">'+"{{ __('The student will be removed from the current batch. Historical records stay unchanged.') }}"+'</div>'
            + '<button class="msm-go" id="msmGo">'+"{{ __('Confirm') }}"+'</button>')
            : ('<div class="msm-warn">'+"{{ __('No other active batch is available in this course.') }}"+'</div>'));

        pane.dataset.mode = canMove ? 'move' : 'add';
        pane.dataset.fromBatch = c.current.length ? c.current[0].id : '';
        var metaEl = document.getElementById('msmMeta');
        function meta(){ if(!metaEl) return; var b = c.eligible.find(function(x){ return x.id == document.getElementById('msmBatch').value; }); if(!b){ metaEl.innerHTML=''; return; }
            var rows = [];
            if(b.schedule)   rows.push(['{{ __('Schedule') }}', b.schedule]);
            if(b.instructor) rows.push(['{{ __('Instructor') }}', b.instructor]);
            rows.push(['{{ __('Status') }}', (b.status||'').charAt(0).toUpperCase()+(b.status||'').slice(1)]);
            if(b.capacity)   rows.push(['{{ __('Capacity') }}', b.seats_used + ' / ' + b.capacity]);
            metaEl.innerHTML = rows.map(function(r){ return '<div class="row"><span>'+esc(r[0])+'</span><span>'+esc(r[1])+'</span></div>'; }).join(''); }
        var bsel = document.getElementById('msmBatch'); if(bsel){ bsel.addEventListener('change', meta); meta(); }

        pane.querySelectorAll('.msm-mode button').forEach(function(btn){ btn.addEventListener('click', function(){
            if(btn.hasAttribute('disabled')) return;
            pane.querySelectorAll('.msm-mode button').forEach(function(x){ x.classList.remove('on'); }); btn.classList.add('on');
            pane.dataset.mode = btn.getAttribute('data-mode');
            var rl = document.getElementById('msmReasonLbl'), wn = document.getElementById('msmWarn');
            if(rl) rl.innerHTML = pane.dataset.mode==='move' ? '{{ __('Reason') }} *' : '{{ __('Reason (optional)') }}';
            if(wn) wn.style.display = pane.dataset.mode==='move' ? 'block' : 'none';
        }); });

        var go = document.getElementById('msmGo');
        if(go) go.addEventListener('click', function(){
            var mode = pane.dataset.mode, newBatch = document.getElementById('msmBatch').value,
                reason = (document.getElementById('msmReason')||{}).value || '';
            if(mode==='move' && !reason.trim()){ show('err', "{{ __('A reason is required when moving a student to another batch.') }}"); return; }
            go.disabled = true; show('', "{{ __('Saving…') }}");
            post(ctxUrl + '/' + curStudent.id + '/batch/reassign', {new_batch_id:newBatch, mode:mode, from_batch_id:pane.dataset.fromBatch, reason:reason}).then(function(r){
                if(r.json && r.json.ok){ show('ok', r.json.message || "{{ __('Updated.') }}"); setTimeout(function(){ location.reload(); }, 1300); }
                else { show('err', (r.json && r.json.message) || "{{ __('Failed.') }}"); go.disabled = false; }
            }).catch(function(){ show('err', "{{ __('Network error.') }}"); go.disabled = false; });
        });
    }
    function show(cls, m){ msg.innerHTML = '<div class="'+(cls==='ok'?'msm-ok':cls==='err'?'msm-err':'')+'">'+m+'</div>'; }
    function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
})();
</script>
@endonce
