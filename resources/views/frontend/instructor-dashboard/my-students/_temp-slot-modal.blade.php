{{-- Reusable "Assign temporary slot" modal (2026-07-15). Include ONCE on any
     coach-panel page with a `.ms-slot-btn` trigger. Fetches the student's
     temp-slot context by AJAX and posts the slot. @once-guarded. --}}
@once
<div id="msSlotModal" style="display:none; position:fixed; inset:0; z-index:9999; align-items:center; justify-content:center; padding:18px;">
    <div data-mss-close style="position:absolute; inset:0; background:rgba(15,23,42,.55);"></div>
    <div style="position:relative; width:100%; max-width:460px; max-height:92vh; overflow:auto; background:#fff; border-radius:16px; padding:22px; box-shadow:0 24px 60px -12px rgba(15,23,42,.4);">
        <button type="button" data-mss-close style="position:absolute; top:12px; right:14px; border:none; background:transparent; font-size:24px; color:#94a3b8; cursor:pointer;">&times;</button>
        <div style="font-size:17px; font-weight:600; color:#1e293b;">{{ __('Assign a temporary slot') }}</div>
        <div id="mssStudent" style="font-size:13px; color:#64748b; margin-bottom:14px;"></div>
        <div id="mssBody" style="font-size:13.5px; color:#334155;"></div>
        <div id="mssMsg" style="margin-top:12px; font-size:13px;"></div>
    </div>
</div>

<style nonce="{{ csp_nonce() }}">
    #msSlotModal .mss-lbl{display:block; font-size:12px; color:#475569; margin:10px 0 3px;}
    #msSlotModal .mss-inp, #msSlotModal .mss-sel{width:100%; box-sizing:border-box; border:1px solid #e2e8f0; border-radius:9px; padding:9px 11px; font-size:13.5px; background:#fff;}
    #msSlotModal .mss-cur{background:#f1f5f9; border-radius:9px; padding:9px 11px; display:flex; justify-content:space-between; align-items:center;}
    #msSlotModal .mss-meta{background:#f8fafc; border-radius:9px; padding:9px 12px; margin-top:6px; font-size:12px; display:flex; flex-direction:column; gap:6px;}
    #msSlotModal .mss-meta .row{display:flex; justify-content:space-between; gap:12px;}
    #msSlotModal .mss-meta .row span:first-child{color:#94a3b8;}
    #msSlotModal .mss-meta .row span:last-child{color:#334155; font-weight:500;}
    #msSlotModal .mss-note{background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; border-radius:9px; padding:9px 11px; font-size:11.5px; margin-top:10px;}
    #msSlotModal .mss-go{width:100%; margin-top:14px; border:none; border-radius:10px; padding:11px; font-size:14px; font-weight:600; color:#fff; background:var(--corp-brand,#4f46e5); cursor:pointer;}
    #msSlotModal .mss-go:disabled{opacity:.6; cursor:not-allowed;}
</style>

<script nonce="{{ csp_nonce() }}">
(function(){
    if (window.__msSlotInit) return; window.__msSlotInit = true;
    var CSRF = (document.querySelector('meta[name="csrf-token"]')||{}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
    var base = "{{ url('instructor/coach-students') }}";
    function post(url, obj){
        var fd = new FormData(); fd.set('_token', CSRF);
        Object.keys(obj).forEach(function(k){ fd.set(k, obj[k]); });
        return fetch(url, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, credentials:'same-origin'})
            .then(function(r){ return r.json().then(function(j){ return {status:r.status, json:j}; }).catch(function(){ return {status:r.status, json:{}}; }); });
    }
    var modal = document.getElementById('msSlotModal'), body = document.getElementById('mssBody'),
        who = document.getElementById('mssStudent'), msg = document.getElementById('mssMsg'), cur = null, ctx = null;
    function open(){ modal.style.display='flex'; document.body.style.overflow='hidden'; }
    function close(){ modal.style.display='none'; document.body.style.overflow=''; msg.innerHTML=''; body.innerHTML=''; }
    modal.querySelectorAll('[data-mss-close]').forEach(function(b){ b.addEventListener('click', close); });

    document.addEventListener('click', function(e){
        var b = e.target.closest('.ms-slot-btn'); if(!b) return;
        cur = {id:b.getAttribute('data-student-id'), name:b.getAttribute('data-student-name')};
        who.textContent = cur.name; body.innerHTML = "{{ __('Loading…') }}"; msg.innerHTML=''; open();
        fetch(base + '/' + cur.id + '/temp-slot/context', {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, credentials:'same-origin'})
            .then(function(r){ return r.json(); }).then(function(d){ ctx = d; render(); })
            .catch(function(){ body.innerHTML = '<div class="msm-err">' + "{{ __('Could not load batches.') }}" + '</div>'; });
    });

    function render(){
        if(!ctx || !ctx.courses || !ctx.courses.length){ body.innerHTML = '<div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:9px;padding:10px 12px;">' + "{{ __('This student is not enrolled in any of your courses.') }}" + '</div>'; return; }
        var sel = ctx.courses.length > 1 ? ('<label class="mss-lbl">{{ __('Course') }}</label><select class="mss-sel" id="mssCourse">' + ctx.courses.map(function(c,i){ return '<option value="'+i+'">'+esc(c.course_title)+'</option>'; }).join('') + '</select>') : '';
        body.innerHTML = sel + '<div id="mssPane"></div>';
        var cs = document.getElementById('mssCourse'); if(cs) cs.addEventListener('change', function(){ pane(+this.value); });
        pane(0);
    }
    function pane(i){
        var c = ctx.courses[i]; var pane = document.getElementById('mssPane');
        if(!c.eligible.length){ pane.innerHTML = '<div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:9px;padding:10px 12px;margin-top:8px;">'+"{{ __('No other active batch is available in this course.') }}"+'</div>'; return; }
        var opts = c.eligible.map(function(b){ return '<option value="'+b.id+'">'+esc(b.title)+'</option>'; }).join('');
        pane.innerHTML =
            '<div class="mss-cur" style="margin-top:8px;"><span style="font-size:11px;color:#94a3b8;">{{ __('Primary batch (unchanged)') }}</span><span style="font-weight:600;">'+esc(c.primary_batch? c.primary_batch.title : "{{ __('No batch') }}")+'</span></div>'
            + '<div style="display:flex;gap:10px;"><div style="flex:1;"><label class="mss-lbl">{{ __('Date') }} *</label><input type="date" class="mss-inp" id="mssDate" min="'+ctx.today+'" value="'+ctx.today+'"></div>'
            + '<div style="flex:1;"><label class="mss-lbl">{{ __('Attend batch') }} *</label><select class="mss-sel" id="mssBatch">'+opts+'</select></div></div>'
            + '<div class="mss-meta" id="mssMeta"></div>'
            + '<label class="mss-lbl">{{ __('Reason') }} <span style="color:#94a3b8;">({{ __('optional') }})</span></label><input class="mss-inp" id="mssReason" placeholder="'+"{{ __('e.g. office shift today') }}"+'">'
            + '<div class="mss-note">'+"{{ __('Applies only to the selected date. Primary batch, past attendance and capacity stay unchanged.') }}"+'</div>'
            + '<button class="mss-go" id="mssGo">'+"{{ __('Assign slot') }}"+'</button>';
        var metaEl = document.getElementById('mssMeta');
        function meta(){ var b = c.eligible.find(function(x){ return x.id == document.getElementById('mssBatch').value; }); if(!b){ metaEl.innerHTML=''; return; }
            var rows = [];
            if(b.schedule) rows.push(['{{ __('Schedule') }}', b.schedule]);
            if(b.instructor) rows.push(['{{ __('Instructor') }}', b.instructor]);
            if(b.capacity) rows.push(['{{ __('Capacity') }}', '{{ __('up to') }} ' + b.capacity]);
            metaEl.innerHTML = rows.map(function(r){ return '<div class="row"><span>'+esc(r[0])+'</span><span>'+esc(r[1])+'</span></div>'; }).join(''); }
        var bs = document.getElementById('mssBatch'); bs.addEventListener('change', meta); meta();

        document.getElementById('mssGo').addEventListener('click', function(){
            var go = this, batch = document.getElementById('mssBatch').value, date = document.getElementById('mssDate').value, reason = document.getElementById('mssReason').value;
            if(!date){ show('err', "{{ __('Please choose a date.') }}"); return; }
            go.disabled = true; show('', "{{ __('Saving…') }}");
            post(base + '/' + cur.id + '/temp-slot', {target_batch_id:batch, slot_date:date, reason:reason}).then(function(r){
                if(r.json && r.json.ok){ show('ok', r.json.message || "{{ __('Assigned.') }}"); setTimeout(function(){ location.reload(); }, 1300); }
                else { show('err', (r.json && r.json.message) || "{{ __('Failed.') }}"); go.disabled = false; }
            }).catch(function(){ show('err', "{{ __('Network error.') }}"); go.disabled = false; });
        });
    }
    function show(cls, m){ msg.innerHTML = '<div class="'+(cls==='ok'?'msm-ok':cls==='err'?'msm-err':'')+'">'+m+'</div>'; }
    function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
})();
</script>
@endonce
