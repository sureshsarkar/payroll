{{-- ────────────────────────────────────────────────────────────────────────
     REUSABLE Booking Enquiry Modal (2026-06-26)
     One shared component, rendered ONCE per coach page (in the coach-site
     master layout). ANY button opens it — no duplicate forms. To trigger from
     a new button anywhere on the site just add:

       <button type="button" class="cs-book-trigger"
               data-source-page="Trainer Detail"
               data-source-button="Book Personal Classes"
               data-trainer="Mansi Rawat" data-trainer-id="42"
               data-class="Personal Yoga" data-time="06:00 AM"
               data-schedule-id="ref-or-index">Book Personal Classes</button>

     The lead posts to coach.booking-enquiry → stored tenant-scoped (host-
     resolved coach) → shown in Coach Panel → Pricing Enquiries.
     Self-contained: own CSS + JS, no external dependency.
     ──────────────────────────────────────────────────────────────────────── --}}
@php
    // Coach id is only a forge-safe FALLBACK — the controller resolves the
    // owning coach from the host first. Resolve defensively from whatever the
    // surrounding layout exposes.
    $bkCoachId = 0;
    if (isset($coach) && is_object($coach) && !empty($coach->id)) {
        $bkCoachId = (int) $coach->id;
    } elseif (isset($page) && is_object($page) && !empty($page->coach_id)) {
        $bkCoachId = (int) $page->coach_id;
    } elseif (!empty($coachId)) {
        $bkCoachId = (int) $coachId;
    }
@endphp

<div class="cs-bkm" id="csBookingModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="csBookingTitle">
    <div class="cs-bkm__overlay" data-bk-close></div>
    <div class="cs-bkm__box" role="document">
        <button type="button" class="cs-bkm__close" data-bk-close aria-label="{{ __('Close') }}">&times;</button>

        <div class="cs-bkm__head">
            <span class="cs-bkm__eyebrow">{{ __('Booking enquiry') }}</span>
            <h3 class="cs-bkm__title" id="csBookingTitle">{{ __('Request a callback') }}</h3>
            <p class="cs-bkm__sub">{{ __('Share your details and our team will reach out to confirm your booking.') }}</p>
        </div>

        {{-- Context chip: what the visitor is booking (filled by the trigger). --}}
        <div class="cs-bkm__context" data-bk-context hidden>
            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
            <span data-bk-context-text></span>
        </div>

        <form class="cs-bkm__form" data-bk-form action="{{ route('coach.booking-enquiry') }}" method="POST" novalidate>
            @csrf
            <input type="hidden" name="coach_id" value="{{ $bkCoachId }}">
            <input type="hidden" name="source_page"   data-bk="source_page">
            <input type="hidden" name="source_button" data-bk="source_button">
            <input type="hidden" name="trainer"       data-bk="trainer">
            <input type="hidden" name="trainer_id"    data-bk="trainer_id">
            <input type="hidden" name="class_name"    data-bk="class_name">
            <input type="hidden" name="class_time"    data-bk="class_time">
            <input type="hidden" name="schedule_id"   data-bk="schedule_id">

            <div class="cs-bkm__field">
                <label for="csBkName">{{ __('Full name') }} <span class="cs-bkm__req">*</span></label>
                <input type="text" id="csBkName" name="name" required maxlength="150" placeholder="{{ __('Your name') }}">
            </div>
            <div class="cs-bkm__row">
                <div class="cs-bkm__field">
                    <label for="csBkEmail">{{ __('Email') }} <span class="cs-bkm__req">*</span></label>
                    <input type="email" id="csBkEmail" name="email" required maxlength="190" placeholder="{{ __('you@example.com') }}">
                </div>
                <div class="cs-bkm__field">
                    <label for="csBkPhone">{{ __('Phone') }} <span class="cs-bkm__req">*</span></label>
                    <input type="tel" id="csBkPhone" name="mobile" required maxlength="40"
                           pattern="[0-9+\-\s()]{7,40}" inputmode="tel" placeholder="{{ __('e.g. +91 98765 43210') }}">
                </div>
            </div>
            <div class="cs-bkm__field">
                <label for="csBkMsg">{{ __('Message') }}</label>
                <textarea id="csBkMsg" name="message" rows="3" maxlength="1000" placeholder="{{ __('Anything you would like us to know (optional)') }}"></textarea>
            </div>

            <button type="submit" class="cs-bkm__submit" data-bk-submit>{{ __('Submit enquiry') }}</button>
            <div class="cs-bkm__msg" data-bk-msg hidden role="status" aria-live="polite"></div>
            <p class="cs-bkm__note"><i class="fa-solid fa-lock" aria-hidden="true"></i> {{ __('Your details are kept private and only shared with this coach.') }}</p>
        </form>
    </div>
</div>

<style nonce="{{ csp_nonce() }}">
    .cs-bkm{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:18px;}
    .cs-bkm[hidden]{display:none;}
    .cs-bkm__overlay{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(2px);}
    .cs-bkm__box{position:relative;width:100%;max-width:520px;max-height:92vh;overflow:auto;background:#fff;border-radius:18px;
        box-shadow:0 24px 60px -12px rgba(15,23,42,.4);padding:26px 26px 22px;animation:csBkIn .22s ease;}
    @keyframes csBkIn{from{opacity:0;transform:translateY(12px) scale(.98);}to{opacity:1;transform:none;}}
    .cs-bkm__close{position:absolute;top:12px;right:14px;border:none;background:transparent;font-size:26px;line-height:1;color:#94a3b8;cursor:pointer;padding:4px 8px;border-radius:8px;}
    .cs-bkm__close:hover{background:#f1f5f9;color:#0f172a;}
    .cs-bkm__head{margin-bottom:14px;padding-right:26px;}
    .cs-bkm__eyebrow{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--brand-primary,#6366F1);margin-bottom:6px;}
    .cs-bkm__title{margin:0 0 4px;font-size:21px;font-weight:800;letter-spacing:-.01em;color:#0f172a;}
    .cs-bkm__sub{margin:0;font-size:13.5px;color:#64748b;line-height:1.5;}
    .cs-bkm__context{display:flex;align-items:center;gap:9px;background:#f5f3ff;border:1px solid #e9e5ff;color:#4338ca;
        border-radius:11px;padding:10px 13px;font-size:13px;font-weight:600;margin-bottom:16px;}
    .cs-bkm__context i{font-size:14px;}
    .cs-bkm__field{margin-bottom:13px;}
    .cs-bkm__row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    @media (max-width:480px){.cs-bkm__row{grid-template-columns:1fr;}.cs-bkm__box{padding:22px 18px 18px;}}
    .cs-bkm__field label{display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:5px;}
    .cs-bkm__req{color:#e11d48;}
    .cs-bkm__field input,.cs-bkm__field textarea{width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:10px;
        padding:11px 12px;font-size:14px;font-family:inherit;color:#0f172a;background:#fff;transition:border-color .15s,box-shadow .15s;}
    .cs-bkm__field input:focus,.cs-bkm__field textarea:focus{outline:none;border-color:var(--brand-primary,#6366F1);box-shadow:0 0 0 3px rgba(99,102,241,.15);}
    .cs-bkm__field input.is-invalid,.cs-bkm__field textarea.is-invalid{border-color:#e11d48;box-shadow:0 0 0 3px rgba(225,29,72,.12);}
    .cs-bkm__submit{width:100%;margin-top:6px;border:none;border-radius:11px;padding:13px;font-size:14.5px;font-weight:700;color:#fff;cursor:pointer;
        background:var(--brand-primary,#6366F1);box-shadow:0 10px 22px -10px rgba(99,102,241,.6);transition:transform .14s,opacity .14s;}
    .cs-bkm__submit:hover{transform:translateY(-1px);}
    .cs-bkm__submit:disabled{opacity:.6;cursor:not-allowed;transform:none;}
    .cs-bkm__msg{margin-top:12px;border-radius:10px;padding:11px 13px;font-size:13px;font-weight:600;}
    .cs-bkm__msg.is-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;}
    .cs-bkm__msg.is-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
    .cs-bkm__note{margin:12px 0 0;font-size:11.5px;color:#94a3b8;text-align:center;}
    .cs-bkm__note i{margin-right:4px;}
</style>

<script nonce="{{ csp_nonce() }}">
(function(){
    var modal = document.getElementById('csBookingModal');
    if (!modal || modal.dataset.bkInit) return;       // guard: init once
    modal.dataset.bkInit = '1';

    var form   = modal.querySelector('[data-bk-form]');
    var msg    = modal.querySelector('[data-bk-msg]');
    var submit = modal.querySelector('[data-bk-submit]');
    var ctx    = modal.querySelector('[data-bk-context]');
    var ctxTxt = modal.querySelector('[data-bk-context-text]');
    var meta   = document.querySelector('meta[name="csrf-token"]');
    var CSRF   = meta ? meta.getAttribute('content') : '';

    function setHidden(key, val){
        var el = modal.querySelector('[data-bk="'+key+'"]');
        if (el) el.value = val || '';
    }

    function openFrom(trigger){
        var d = trigger.dataset || {};
        setHidden('source_page',   d.sourcePage || document.title || '');
        setHidden('source_button', d.sourceButton || (trigger.textContent || '').trim());
        setHidden('trainer',       d.trainer || '');
        setHidden('trainer_id',    d.trainerId || '');
        setHidden('class_name',    d.class || '');
        setHidden('class_time',    d.time || '');
        setHidden('schedule_id',   d.scheduleId || '');

        // Build a friendly context line.
        var bits = [];
        if (d.class)   bits.push(d.class);
        if (d.time)    bits.push(d.time);
        if (d.trainer) bits.push("{{ __('with') }} " + d.trainer);
        if (bits.length && ctx && ctxTxt){ ctxTxt.textContent = bits.join(' · '); ctx.hidden = false; }
        else if (ctx) { ctx.hidden = true; }

        if (msg){ msg.hidden = true; msg.textContent = ''; msg.className = 'cs-bkm__msg'; }
        modal.hidden = false; modal.setAttribute('aria-hidden','false');
        document.body.style.overflow = 'hidden';
        var first = modal.querySelector('#csBkName'); if (first) first.focus();
    }

    function close(){
        modal.hidden = true; modal.setAttribute('aria-hidden','true');
        document.body.style.overflow = '';
    }

    // Delegated open — works for current AND future trigger buttons.
    document.addEventListener('click', function(e){
        var t = e.target.closest('.cs-book-trigger, [data-cs-book]');
        if (!t) return;
        e.preventDefault();
        openFrom(t);
    });
    modal.querySelectorAll('[data-bk-close]').forEach(function(b){ b.addEventListener('click', close); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && !modal.hidden) close(); });

    form.addEventListener('submit', function(e){
        e.preventDefault();
        if (submit.disabled) return;                  // dup-submit guard (in-flight)

        // Lightweight frontend validation with inline cues.
        var ok = true;
        ['csBkName','csBkEmail','csBkPhone'].forEach(function(id){
            var el = modal.querySelector('#'+id);
            var bad = !el.value.trim() || (el.type === 'email' && !/^\S+@\S+\.\S+$/.test(el.value))
                      || (id === 'csBkPhone' && (el.value.replace(/\D/g,'').length < 7));
            el.classList.toggle('is-invalid', bad);
            if (bad) ok = false;
        });
        if (!ok){
            if (msg){ msg.hidden = false; msg.className = 'cs-bkm__msg is-err'; msg.textContent = "{{ __('Please fill in your name, a valid email and phone number.') }}"; }
            return;
        }

        var fd = new FormData(form);
        fd.set('_token', CSRF);
        var orig = submit.textContent;
        submit.disabled = true; submit.textContent = "{{ __('Sending…') }}";

        fetch(form.getAttribute('action'), {
            method:'POST', body: fd, credentials:'same-origin',
            headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
        })
        .then(function(r){ return r.json().catch(function(){ return {ok:false}; }); })
        .then(function(data){
            if (data && data.ok){
                form.reset();
                if (ctx) ctx.hidden = true;
                if (msg){ msg.hidden = false; msg.className = 'cs-bkm__msg is-ok'; msg.textContent = data.message || "{{ __('Thank you! Your booking enquiry has been submitted successfully.') }}"; }
                setTimeout(close, 2400);
            } else {
                if (msg){ msg.hidden = false; msg.className = 'cs-bkm__msg is-err';
                          msg.textContent = (data && data.message) || "{{ __('Something went wrong. Please try again.') }}"; }
            }
        })
        .catch(function(){ if (msg){ msg.hidden = false; msg.className = 'cs-bkm__msg is-err'; msg.textContent = "{{ __('Network error. Please try again.') }}"; } })
        .finally(function(){ submit.disabled = false; submit.textContent = orig; });
    });
})();
</script>
