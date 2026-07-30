{{-- Shared "Book Personal Class Session" modal + payment (2026-07-15, Phase 6).
     Include ONCE on any coach-site surface that has a `.td-book` trigger button
     (the dedicated Trainer Detail route AND the trainer_booking_v1 builder
     section both reuse this). Expects: $trainer (CoachTrainer), $packages
     (Collection), $coach (optional — forge-safe fallback). Wrapped in @once so
     multiple includes on one page render a single modal. --}}
@once
@php
    // Normalized, data-source-agnostic inputs so BOTH the dedicated Trainer Detail
    // route (entity) and the trainer_booking_v1 builder section (self-contained)
    // reuse this modal. Callers pass plain arrays — no model coupling.
    $bkTitle       = $bkTitle       ?? __('Trainer');
    $bkCoachId     = (int) ($bkCoachId ?? 0);
    $bkHidden      = is_array($bkHidden ?? null) ? $bkHidden : [];        // e.g. ['trainer_id'=>5] or ['section_id'=>12]
    $bkPlanTypes   = array_values(array_filter((array) ($bkPlanTypes   ?? []))) ?: ['Online', 'Offline'];
    $bkCourseTypes = array_values(array_filter((array) ($bkCourseTypes ?? []))) ?: ['Individual Plan', 'Couple Plan'];
    $bkReasons     = array_values(array_filter((array) ($bkReasons     ?? []))) ?: ['Fitness', 'Weight Loss', 'Problem'];
    $bkPackages    = array_values((array) ($bkPackages ?? []));            // each: value,label,price,sym
@endphp
<div class="tdbk" id="tdBookModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tdBkTitle">
    <div class="tdbk__overlay" data-tdbk-close></div>
    <div class="tdbk__box" role="document">
        <button type="button" class="tdbk__close" data-tdbk-close aria-label="{{ __('Close') }}">&times;</button>
        <div class="tdbk__head">
            <span class="tdbk__eyebrow">{{ __('Personal session') }}</span>
            <h3 class="tdbk__title" id="tdBkTitle">{{ __('Book with') }} {{ $bkTitle }}</h3>
        </div>

        <div class="tdbk__amount" data-tdbk-amount hidden>
            <span data-tdbk-amount-label></span>
            <strong data-tdbk-amount-val></strong>
        </div>

        <form class="tdbk__form" data-tdbk-form novalidate>
            @csrf
            <input type="hidden" name="coach_id" value="{{ $bkCoachId }}">
            @foreach($bkHidden as $hk => $hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
            <input type="hidden" name="source_page" value="{{ __('Trainer Detail') }} — {{ $bkTitle }}">
            <input type="hidden" name="source_button" value="{{ __('Book Personal Class Session') }}">

            <div class="tdbk__grid">
                <div>
                    <label class="tdbk__lbl">{{ __('Plan Type') }} *
                        <select name="plan_type" class="tdbk__inp" required>
                            @foreach($bkPlanTypes as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </label>
                    <label class="tdbk__lbl">{{ __('Course Type') }} *
                        <select name="course_type" class="tdbk__inp" required>
                            @foreach($bkCourseTypes as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </label>
                    <label class="tdbk__lbl">{{ __('Choose Session') }} *
                        <select name="package_id" class="tdbk__inp" data-tdbk-pkg required>
                            @foreach($bkPackages as $p)
                                <option value="{{ $p['value'] }}" data-price="{{ (float) ($p['price'] ?? 0) }}" data-sym="{{ $p['sym'] ?? '₹' }}">{{ $p['label'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="tdbk__lbl">{{ __('Select Gender') }} *
                        <select name="gender" class="tdbk__inp" required>
                            <option value="Male">{{ __('Male') }}</option>
                            <option value="Female">{{ __('Female') }}</option>
                            <option value="Other">{{ __('Other') }}</option>
                        </select>
                    </label>
                    <div class="tdbk__row">
                        <label class="tdbk__lbl">{{ __('Height') }} *<input type="text" name="height" class="tdbk__inp" required maxlength="20" placeholder="{{ __('e.g. 170') }}"></label>
                        <label class="tdbk__lbl">{{ __('Weight') }} *<input type="text" name="weight" class="tdbk__inp" required maxlength="20" placeholder="{{ __('e.g. 65') }}"></label>
                    </div>
                </div>
                <div>
                    <label class="tdbk__lbl">{{ __('Name') }} *<input type="text" name="name" class="tdbk__inp" required maxlength="150" placeholder="{{ __('Enter Name') }}"></label>
                    <label class="tdbk__lbl">{{ __('Email') }} *<input type="email" name="email" class="tdbk__inp" required maxlength="190" placeholder="{{ __('Enter Email') }}"></label>
                    <label class="tdbk__lbl">{{ __('Mobile') }} *<input type="tel" name="mobile" class="tdbk__inp" required maxlength="40" pattern="[0-9+\-\s()]{7,40}" inputmode="tel" placeholder="{{ __('Enter Mobile') }}"></label>
                    <label class="tdbk__lbl">{{ __('Reason') }} *
                        <select name="reason" class="tdbk__inp" required>
                            @foreach($bkReasons as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </label>
                    <label class="tdbk__lbl">{{ __('Problem Description') }}<textarea name="problem" class="tdbk__inp" rows="3" maxlength="1000" placeholder="{{ __('Enter Problem Description') }}"></textarea></label>
                </div>
            </div>

            <button type="submit" class="tdbk__submit" data-tdbk-submit><i class="fa-solid fa-lock" aria-hidden="true"></i> <span data-tdbk-submit-text>{{ __('Submit') }}</span></button>
            <div class="tdbk__msg" data-tdbk-msg hidden role="status" aria-live="polite"></div>
            <p class="tdbk__note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> {{ __('Amount is verified on our server · secured by Razorpay.') }}</p>
        </form>
    </div>
</div>

<style nonce="{{ csp_nonce() }}">
    .tdbk{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:18px;}
    .tdbk[hidden]{display:none;}
    .tdbk__overlay{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(2px);}
    .tdbk__box{position:relative;width:100%;max-width:580px;max-height:92vh;overflow:auto;background:#fff;border-radius:18px;box-shadow:0 24px 60px -12px rgba(15,23,42,.4);padding:24px;}
    .tdbk__grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    @media (max-width:520px){.tdbk__grid{grid-template-columns:1fr;gap:0;}}
    .tdbk__close{position:absolute;top:12px;right:14px;border:none;background:transparent;font-size:26px;line-height:1;color:#94a3b8;cursor:pointer;}
    .tdbk__eyebrow{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--brand-primary,#6366F1);}
    .tdbk__title{margin:4px 0 0;font-size:19px;font-weight:800;color:#0f172a;}
    .tdbk__amount{display:flex;justify-content:space-between;align-items:center;background:#f5f3ff;border:1px solid #e9e5ff;border-radius:11px;padding:10px 13px;margin:14px 0;font-size:13px;color:#4338ca;font-weight:600;}
    .tdbk__amount strong{font-size:16px;}
    .tdbk__lbl{display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:11px;}
    .tdbk__row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    @media (max-width:460px){.tdbk__row{grid-template-columns:1fr;}}
    .tdbk__inp{width:100%;box-sizing:border-box;margin-top:5px;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:14px;font-family:inherit;color:#0f172a;background:#fff;}
    .tdbk__inp:focus{outline:none;border-color:var(--brand-primary,#6366F1);box-shadow:0 0 0 3px rgba(99,102,241,.15);}
    .tdbk__inp.is-invalid{border-color:#e11d48;box-shadow:0 0 0 3px rgba(225,29,72,.12);}
    .tdbk__submit{width:100%;margin-top:4px;border:none;border-radius:11px;padding:13px;font-size:14.5px;font-weight:700;color:#fff;cursor:pointer;background:var(--brand-primary,#6366F1);box-shadow:0 10px 22px -10px rgba(99,102,241,.6);}
    .tdbk__submit:disabled{opacity:.6;cursor:not-allowed;}
    .tdbk__msg{margin-top:12px;border-radius:10px;padding:11px 13px;font-size:13px;font-weight:600;}
    .tdbk__msg.is-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;}
    .tdbk__msg.is-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
    .tdbk__note{margin:11px 0 0;font-size:11.5px;color:#94a3b8;text-align:center;}
</style>

<script nonce="{{ csp_nonce() }}">
(function(){
    var modal = document.getElementById('tdBookModal');
    if (!modal || modal.dataset.init) return;
    modal.dataset.init = '1';
    var CSRF = (document.querySelector('meta[name="csrf-token"]')||{}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
    var f = modal.querySelector('[data-tdbk-form]');
    var msg = modal.querySelector('[data-tdbk-msg]');
    var pkg = modal.querySelector('[data-tdbk-pkg]');
    var amt = modal.querySelector('[data-tdbk-amount]');
    var amtVal = modal.querySelector('[data-tdbk-amount-val]');
    var amtLbl = modal.querySelector('[data-tdbk-amount-label]');
    var submit = modal.querySelector('[data-tdbk-submit]');
    var submitTxt = modal.querySelector('[data-tdbk-submit-text]');

    function open(){ modal.hidden=false; modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
    function close(){ modal.hidden=true; modal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
    function show(cls,m){ if(!msg)return; msg.hidden=false; msg.className='tdbk__msg '+cls; msg.textContent=m; }

    function syncAmount(){
        var o = pkg.options[pkg.selectedIndex]; if(!o){ return; }
        var price = parseFloat(o.getAttribute('data-price')||'0'); var sym = o.getAttribute('data-sym')||'₹';
        if (price > 0){
            amt.hidden=false; amtLbl.textContent=o.textContent; amtVal.textContent=sym+price.toLocaleString('en-IN');
            submitTxt.textContent='{{ __('Pay') }} '+sym+price.toLocaleString('en-IN')+' & {{ __('confirm') }}';
        } else {
            amt.hidden=true; submitTxt.textContent='{{ __('Request booking') }}';
        }
    }
    if (pkg) pkg.addEventListener('change', syncAmount);

    document.addEventListener('click', function(e){
        var t = e.target.closest('.td-book');
        if (t){
            e.preventDefault();
            var pid = t.getAttribute('data-package-id');
            if (pid && pkg){ pkg.value = pid; }
            syncAmount();
            if (msg){ msg.hidden=true; msg.textContent=''; }
            open(); return;
        }
        if (e.target.closest('[data-tdbk-close]')) close();
    });
    document.addEventListener('keydown', function(e){ if (e.key==='Escape' && !modal.hidden) close(); });

    function ensureRzp(cb){
        if (typeof Razorpay !== 'undefined'){ cb(true); return; }
        var s = document.getElementById('cs-rzp-checkout-js');
        if (!s){ s=document.createElement('script'); s.id='cs-rzp-checkout-js'; s.src='https://checkout.razorpay.com/v1/checkout.js'; document.head.appendChild(s); }
        var n=0, iv=setInterval(function(){ if (typeof Razorpay!=='undefined'){ clearInterval(iv); cb(true);} else if (++n>120){ clearInterval(iv); cb(false);} },100);
    }
    function post(url, obj){
        var fd=new FormData(); fd.set('_token',CSRF);
        Object.keys(obj).forEach(function(k){ fd.set(k,obj[k]); });
        return fetch(url,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.json().catch(function(){return {ok:false};});});
    }

    f.addEventListener('submit', function(e){
        e.preventDefault();
        var ok=true;
        ['name','email','mobile','height','weight'].forEach(function(n){
            var el=f.querySelector('[name="'+n+'"]');
            if(!el) return;
            var bad=!el.value.trim() || (n==='email' && !/^\S+@\S+\.\S+$/.test(el.value)) || (n==='mobile' && el.value.replace(/\D/g,'').length<7);
            el.classList.toggle('is-invalid',bad); if(bad) ok=false;
        });
        if(!ok){ show('err','{{ __('Please fill in all required fields (name, email, phone, height, weight).') }}'); return; }

        var orig=submitTxt.textContent; submit.disabled=true; submitTxt.textContent='{{ __('Sending…') }}';
        var fd=new FormData(f); fd.set('_token',CSRF);
        fetch('{{ route('coach.trainer-booking') }}',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'})
          .then(function(r){return r.json().catch(function(){return {ok:false};});})
          .then(function(d){
            if (d && d.ok && d.mode==='payment'){ startPay(d); return; }
            if (d && d.ok){ f.reset(); show('ok', d.message||'{{ __('Thank you!') }}'); setTimeout(close,2400); }
            else { show('err', (d&&d.message)||'{{ __('Something went wrong. Please try again.') }}'); }
          })
          .catch(function(){ show('err','{{ __('Network error. Please try again.') }}'); })
          .finally(function(){ submit.disabled=false; submitTxt.textContent=orig; });
    });

    function startPay(d){
        show('ok','{{ __('Opening secure payment…') }}');
        ensureRzp(function(ok){
            if(!ok){ show('err','{{ __('Payment library failed to load. Please try again.') }}'); return; }
            var opt={ key:d.key, order_id:d.order_id, amount:d.amount, currency:d.currency||'INR', name:d.name||document.title, description:d.description||'', prefill:d.prefill||{}, theme:{color:d.theme||'#6366F1'},
                modal:{ ondismiss:function(){ post(d.cancel_url,{payment_id:d.payment_id}); show('err','{{ __('Payment cancelled. You can try again.') }}'); } },
                handler:function(resp){
                    post(d.verify_url,{payment_id:d.payment_id, razorpay_payment_id:resp.razorpay_payment_id, razorpay_order_id:resp.razorpay_order_id, razorpay_signature:resp.razorpay_signature})
                      .then(function(j){ if(j.ok){ f.reset(); show('ok', j.message||'{{ __('Payment successful!') }}'); setTimeout(close,2600); } else { show('err', j.message||'{{ __('We could not confirm your payment. If money was deducted, please contact us.') }}'); } })
                      .catch(function(){ show('err','{{ __('Network error while confirming payment. Please contact us.') }}'); });
                }
            };
            try { (new Razorpay(opt)).open(); } catch(e){ show('err','{{ __('Could not open payment window.') }}'); }
        });
    }
})();
</script>
@endonce
