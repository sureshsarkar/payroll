{{--
    "Book Your Trial Session" popup (2026-07-03).

    Fully white-label + coach-scoped: renders ONLY when the resolved coach has
    enabled it in their panel. All copy, price and time slots come from the
    coach's own config. Auto-shows per the coach's frequency/delay settings.
    Submits to the public `coach.trial-session` endpoint which creates a
    coach-scoped enquiry (+ a pending payment when a price is configured).
--}}
@php
    $tsCoachId  = (int) ($coachId ?? 0);
    $tsSettings = null;
    $tsSlots    = collect();
    if ($tsCoachId > 0) {
        try {
            $tsSettings = \App\Models\CoachTrialSetting::where('coach_id', $tsCoachId)->first();
            if ($tsSettings && $tsSettings->is_enabled) {
                $tsSlots = \App\Models\CoachTrialSlot::forCoach($tsCoachId)->active()->ordered()->get();
            }
        } catch (\Throwable $e) {
            $tsSettings = null;
        }
    }
    $tsShow = $tsSettings && $tsSettings->is_enabled;
    $tsPrimary = (isset($brand) && is_object($brand) ? ($brand->primaryColor ?? null) : null) ?: '#6366F1';
@endphp

@if($tsShow)
@php
    $tsIcon  = $tsSettings->currency_icon ?: '₹';
    $tsPrice = (float) $tsSettings->price;
    $tsCharges = $tsSettings->require_payment && $tsPrice > 0;
@endphp
<div class="cs-ts" id="csTrialPopup" hidden role="dialog" aria-modal="true" aria-labelledby="csTrialTitle"
     data-auto="{{ $tsSettings->auto_show ? '1' : '0' }}"
     data-delay="{{ (int) $tsSettings->show_delay_seconds }}"
     data-freq="{{ $tsSettings->show_frequency }}"
     data-coach="{{ $tsCoachId }}">
    <div class="cs-ts__overlay" data-ts-close></div>
    <div class="cs-ts__box" role="document">
        <button type="button" class="cs-ts__close" data-ts-close aria-label="{{ __('Close') }}">&times;</button>

        <div class="cs-ts__head">
            <h2 class="cs-ts__title" id="csTrialTitle">{{ $tsSettings->displayTitle() }}</h2>
            <p class="cs-ts__sub">{{ $tsSettings->displaySubtitle() }}</p>
        </div>

        <div class="cs-ts__body">
            <div class="cs-ts__alert" data-ts-alert hidden></div>

            <form class="cs-ts__form" data-ts-form novalidate
                  action="{{ route('coach.trial-session') }}" method="POST">
                @csrf
                <input type="hidden" name="coach_id" value="{{ $tsCoachId }}">
                <input type="hidden" name="source_page" data-ts-source>

                <div class="cs-ts__grid">
                    <div class="cs-ts__field">
                        <label>{{ __('Plan Type') }} *</label>
                        <select name="plan_type" required>
                            <option value="">{{ __('Select') }}</option>
                            <option value="online">{{ __('Online') }}</option>
                            <option value="offline">{{ __('Offline') }}</option>
                        </select>
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Course Type') }} *</label>
                        <select name="course_type" required>
                            <option value="">{{ __('Select') }}</option>
                            <option value="individual">{{ __('Individual Plan') }}</option>
                            <option value="couple">{{ __('Couple Plan') }}</option>
                        </select>
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field cs-ts__field--full">
                        <label>{{ __('Time Slot') }} *</label>
                        <select name="slot_id" required>
                            <option value="">{{ __('Select a time slot') }}</option>
                            @foreach($tsSlots as $slot)
                                <option value="{{ $slot->id }}" data-label="{{ $slot->label }}">{{ $slot->label }}</option>
                            @endforeach
                        </select>
                        @if($tsSlots->isEmpty())
                            <small style="color:#94a3b8;">{{ __('No slots configured yet.') }}</small>
                        @endif
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Name') }} *</label>
                        <input type="text" name="name" maxlength="150" required>
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Email') }} *</label>
                        <input type="email" name="email" maxlength="190" required>
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Mobile Number') }} *</label>
                        <input type="tel" name="mobile" maxlength="40" required>
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Gender') }} *</label>
                        <select name="gender" required>
                            <option value="">{{ __('Select') }}</option>
                            <option value="male">{{ __('Male') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                        </select>
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Height') }}</label>
                        <input type="number" name="height" step="0.01" min="0" inputmode="decimal" placeholder="{{ __('cm') }}">
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Weight') }}</label>
                        <input type="number" name="weight" step="0.01" min="0" inputmode="decimal" placeholder="{{ __('kg') }}">
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field">
                        <label>{{ __('Reason') }} *</label>
                        <select name="reason" required>
                            <option value="">{{ __('Select') }}</option>
                            <option value="fitness">{{ __('Fitness') }}</option>
                            <option value="problem">{{ __('Problem') }}</option>
                        </select>
                        <span class="cs-ts__err" data-err></span>
                    </div>

                    <div class="cs-ts__field cs-ts__field--full">
                        <label>{{ __('Problem Description') }}</label>
                        <textarea name="problem_description" rows="2" maxlength="1000"></textarea>
                        <span class="cs-ts__err" data-err></span>
                    </div>
                </div>

                <button type="submit" class="cs-ts__submit" data-ts-submit style="background:{{ $tsPrimary }};">
                    <span data-ts-btnlabel>
                        @if($tsCharges)
                            {{ __('Proceed to Pay') }} {{ $tsIcon }}{{ rtrim(rtrim(number_format($tsPrice,2),'0'),'.') }}
                        @else
                            {{ __('Book My Trial Session') }}
                        @endif
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>

<style nonce="{{ csp_nonce() }}">
    .cs-ts{position:fixed;inset:0;z-index:99990;display:flex;align-items:center;justify-content:center;padding:16px;}
    .cs-ts[hidden]{display:none;}
    .cs-ts__overlay{position:absolute;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(2px);}
    .cs-ts__box{position:relative;width:100%;max-width:560px;max-height:92vh;overflow:hidden;background:#fff;border-radius:18px;
        box-shadow:0 30px 70px -20px rgba(15,23,42,.5);display:flex;flex-direction:column;animation:csTsIn .25s ease;}
    @keyframes csTsIn{from{opacity:0;transform:translateY(14px) scale(.98);}to{opacity:1;transform:none;}}
    .cs-ts__close{position:absolute;top:10px;right:14px;z-index:2;border:none;background:transparent;font-size:28px;line-height:1;color:rgba(255,255,255,.9);cursor:pointer;}
    .cs-ts__head{padding:22px 26px;background:linear-gradient(135deg,{{ $tsPrimary }},#0f172a);color:#fff;}
    .cs-ts__title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.01em;}
    .cs-ts__sub{margin:6px 0 0;font-size:14px;opacity:.92;}
    .cs-ts__body{padding:20px 26px 24px;overflow-y:auto;}
    .cs-ts__alert{border-radius:10px;padding:10px 12px;font-size:13.5px;margin-bottom:14px;}
    .cs-ts__alert.is-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
    .cs-ts__alert.is-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;}
    .cs-ts__grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 14px;}
    .cs-ts__field{display:flex;flex-direction:column;}
    .cs-ts__field--full{grid-column:1 / -1;}
    .cs-ts__field label{font-size:12.5px;font-weight:600;color:#334155;margin-bottom:5px;}
    .cs-ts__field input,.cs-ts__field select,.cs-ts__field textarea{
        border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:14px;color:#0f172a;background:#fff;width:100%;font-family:inherit;}
    .cs-ts__field input:focus,.cs-ts__field select:focus,.cs-ts__field textarea:focus{outline:none;border-color:{{ $tsPrimary }};box-shadow:0 0 0 3px rgba(99,102,241,.12);}
    .cs-ts__field.is-invalid input,.cs-ts__field.is-invalid select,.cs-ts__field.is-invalid textarea{border-color:#ef4444;}
    .cs-ts__err{color:#ef4444;font-size:11.5px;margin-top:4px;min-height:0;}
    .cs-ts__submit{margin-top:18px;width:100%;border:none;border-radius:12px;color:#fff;font-size:15px;font-weight:700;padding:13px;cursor:pointer;transition:opacity .15s ease,transform .05s ease;}
    .cs-ts__submit:hover{opacity:.94;}
    .cs-ts__submit:active{transform:translateY(1px);}
    .cs-ts__submit[disabled]{opacity:.6;cursor:not-allowed;}
    @media (max-width:520px){
        .cs-ts__grid{grid-template-columns:1fr;}
        .cs-ts__head{padding:18px 20px;}
        .cs-ts__title{font-size:19px;}
        .cs-ts__body{padding:16px 18px 20px;}
    }
</style>

<script nonce="{{ csp_nonce() }}">
(function(){
    var popup = document.getElementById('csTrialPopup');
    if(!popup) return;
    var form   = popup.querySelector('[data-ts-form]');
    var alertB = popup.querySelector('[data-ts-alert]');
    var btn    = popup.querySelector('[data-ts-submit]');
    var coach  = popup.getAttribute('data-coach') || '0';
    var freq   = popup.getAttribute('data-freq') || 'session';
    var key    = 'cs_ts_seen_' + coach;

    /* ----- auto-show gating (frequency + delay) ----- */
    function seenRecently(){
        try{
            if(freq === 'always') return false;
            if(freq === 'session'){ return sessionStorage.getItem(key) === '1'; }
            var raw = localStorage.getItem(key);
            if(!raw) return false;
            var windowMs = (freq === 'daily') ? 864e5 : 2592e6; /* 1d : 30d */
            return (Date.now() - parseInt(raw,10)) < windowMs;
        }catch(e){ return false; }
    }
    function markSeen(){
        try{
            if(freq === 'session'){ sessionStorage.setItem(key,'1'); }
            else if(freq !== 'always'){ localStorage.setItem(key, String(Date.now())); }
        }catch(e){}
    }
    function open(){
        popup.hidden = false;
        document.body.style.overflow = 'hidden';
        markSeen();
        var f = form.querySelector('select[name="plan_type"]'); if(f) f.focus();
    }
    function close(){ popup.hidden = true; document.body.style.overflow = ''; }

    popup.querySelectorAll('[data-ts-close]').forEach(function(el){ el.addEventListener('click', close); });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !popup.hidden) close(); });

    if(popup.getAttribute('data-auto') === '1' && !seenRecently()){
        var delay = (parseInt(popup.getAttribute('data-delay'),10) || 0) * 1000;
        setTimeout(open, delay);
    }
    /* Allow any element with .cs-trial-trigger to open it manually. */
    document.addEventListener('click', function(e){
        var t = e.target.closest('.cs-trial-trigger,[data-cs-trial]');
        if(!t) return; e.preventDefault(); open();
    });

    /* ----- helpers ----- */
    var src = form.querySelector('[data-ts-source]'); if(src) src.value = (document.title || '') + ' | ' + location.pathname;
    function fieldEl(name){ return form.querySelector('[name="'+name+'"]'); }
    function setErr(name, msg){
        var el = fieldEl(name); if(!el) return;
        var wrap = el.closest('.cs-ts__field'); if(!wrap) return;
        wrap.classList.toggle('is-invalid', !!msg);
        var e = wrap.querySelector('[data-err]'); if(e) e.textContent = msg || '';
    }
    function clearErrs(){ form.querySelectorAll('.cs-ts__field').forEach(function(w){ w.classList.remove('is-invalid'); var e=w.querySelector('[data-err]'); if(e) e.textContent=''; }); alertB.hidden = true; alertB.className='cs-ts__alert'; }
    function showAlert(msg, ok){ alertB.hidden=false; alertB.className='cs-ts__alert '+(ok?'is-success':'is-error'); alertB.textContent = msg; }

    function validate(){
        clearErrs();
        var ok = true, v = function(n){ var el=fieldEl(n); return el ? String(el.value||'').trim() : ''; };
        ['plan_type','course_type','slot_id','name','email','mobile','gender','reason'].forEach(function(n){
            if(!v(n)){ setErr(n, '{{ __('This field is required.') }}'); ok=false; }
        });
        var email = v('email');
        if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ setErr('email','{{ __('Enter a valid email.') }}'); ok=false; }
        var mobile = v('mobile');
        if(mobile && (mobile.replace(/\D/g,'').length < 7)){ setErr('mobile','{{ __('Enter a valid mobile number.') }}'); ok=false; }
        ['height','weight'].forEach(function(n){ var val=v(n); if(val && isNaN(Number(val))){ setErr(n,'{{ __('Numbers only.') }}'); ok=false; } });
        return ok;
    }

    var submitting = false;
    form.addEventListener('submit', function(e){
        e.preventDefault();
        if(submitting) return;
        if(!validate()){ return; }

        submitting = true; btn.disabled = true;
        var label = btn.querySelector('[data-ts-btnlabel]'); var original = label ? label.textContent : '';
        if(label) label.textContent = '{{ __('Please wait…') }}';

        var slotSel = fieldEl('slot_id');
        var fd = new FormData(form);
        if(slotSel && slotSel.selectedOptions[0]){ fd.set('time_slot', slotSel.selectedOptions[0].getAttribute('data-label') || ''); }

        fetch(form.getAttribute('action'), {
            method:'POST',
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: fd
        }).then(function(r){ return r.json().then(function(j){ return {status:r.status, body:j}; }); })
        .then(function(res){
            var j = res.body || {};
            if(res.status === 422 && j.errors){
                Object.keys(j.errors).forEach(function(k){ setErr(k, j.errors[k][0]); });
                submitting=false; btn.disabled=false; if(label) label.textContent=original;
                return;
            }
            if(!j.ok){
                showAlert(j.message || '{{ __('Something went wrong. Please try again.') }}', false);
                submitting=false; btn.disabled=false; if(label) label.textContent=original;
                return;
            }
            /* Payment path (Razorpay) is handled by the checkout script (Phase 3). */
            if(j.mode === 'payment' && window.csTrialPay){
                window.csTrialPay(j, { onDismiss:function(){ submitting=false; btn.disabled=false; if(label) label.textContent=original; } });
                return;
            }
            if(j.redirect){ window.location = j.redirect; return; }
            /* Free / no-charge success. */
            form.style.display='none';
            showAlert(j.message || '{{ __('Thank you! Your trial session has been booked.') }}', true);
        }).catch(function(){
            showAlert('{{ __('Network error. Please try again.') }}', false);
            submitting=false; btn.disabled=false; if(label) label.textContent=original;
        });
    });
})();
</script>

@if($tsCharges)
<script nonce="{{ csp_nonce() }}" src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script nonce="{{ csp_nonce() }}">
/*
 * Razorpay checkout bridge for the trial popup. The popup JS calls
 * window.csTrialPay(payload) after the server creates the order; we open
 * Razorpay, then POST the signed result to the verify endpoint. The amount is
 * fixed by the server-created order, so it cannot be altered here.
 */
(function(){
    var popup = document.getElementById('csTrialPopup');
    if(!popup) return;
    var form   = popup.querySelector('[data-ts-form]');
    var alertB = popup.querySelector('[data-ts-alert]');
    var token  = (form.querySelector('input[name="_token"]') || {}).value || '';

    function showAlert(msg, ok){
        if(!alertB) return;
        alertB.hidden=false; alertB.className='cs-ts__alert '+(ok?'is-success':'is-error'); alertB.textContent=msg;
    }

    window.csTrialPay = function(payload, opts){
        opts = opts || {};
        if(typeof Razorpay === 'undefined'){
            showAlert('{{ __('Payment library failed to load. Please try again.') }}', false);
            if(opts.onDismiss) opts.onDismiss();
            return;
        }
        var options = {
            key:        payload.key,
            order_id:   payload.order_id,
            amount:     payload.amount,
            currency:   payload.currency || 'INR',
            name:       payload.name || document.title,
            description:payload.description || '',
            prefill:    payload.prefill || {},
            theme:      { color: payload.theme || '{{ $tsPrimary }}' },
            modal:      { ondismiss: function(){ if(opts.onDismiss) opts.onDismiss(); } },
            handler: function(resp){
                var fd = new FormData();
                fd.append('_token', token);
                fd.append('payment_id', payload.payment_id);
                fd.append('razorpay_payment_id', resp.razorpay_payment_id);
                fd.append('razorpay_order_id', resp.razorpay_order_id);
                fd.append('razorpay_signature', resp.razorpay_signature);
                fetch('{{ route('coach.trial-session.verify') }}', {
                    method:'POST',
                    headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
                    body: fd
                }).then(function(r){ return r.json(); }).then(function(j){
                    if(j.ok){
                        form.style.display='none';
                        showAlert(j.message || '{{ __('Payment successful! Your trial session is confirmed.') }}', true);
                    } else {
                        showAlert(j.message || '{{ __('We could not confirm your payment. If money was deducted, please contact us.') }}', false);
                        if(opts.onDismiss) opts.onDismiss();
                    }
                }).catch(function(){
                    showAlert('{{ __('Network error while confirming payment. Please contact us.') }}', false);
                    if(opts.onDismiss) opts.onDismiss();
                });
            }
        };
        try { (new Razorpay(options)).open(); }
        catch(e){ showAlert('{{ __('Could not open payment window.') }}', false); if(opts.onDismiss) opts.onDismiss(); }
    };
})();
</script>
@endif
@endif
