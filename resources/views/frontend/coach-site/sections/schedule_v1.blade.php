{{-- Class Schedule v1 — responsive card grid of upcoming classes. Each card:
     time badge + instructor badge, title, focus/desc, CTA, optional status.
     Per-item badge/card colors. Mobile carousel option. Styling: coach-site.css
     (.cs-schedule*). --}}
@php
    $c        = $content;
    $cols     = (int) ($c['columns'] ?? 3); $cols = in_array($cols, [1,2,3,4], true) ? $cols : 3;
    $carousel = ! array_key_exists('carousel', $c) || ! empty($c['carousel']);
    $items    = collect($c['items'] ?? [])->filter(function ($i) {
        return trim((string)($i['title'] ?? '')) !== '' || trim((string)($i['time'] ?? '')) !== '';
    })->values();

    // 2026-07-14 — "Book a Session" popup config. When at least one priced period
    // is configured, the class cards open this booking form (+ payment) instead of
    // the plain callback modal. All options are coach-editable + tenant-specific.
    $sbPeriods = collect($c['periods'] ?? [])->filter(fn ($p) => trim((string)($p['label'] ?? '')) !== '')->values();
    $sbEnabled = $sbPeriods->isNotEmpty();
    $sbPlanTypes   = collect($c['plan_types'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->values();
    $sbCourseTypes = collect($c['course_types'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->values();
    $sbReasons     = collect($c['reasons'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->values();
    $sbTitle    = trim((string)($c['book_title'] ?? '')) ?: __('Book a session');
    $sbSubtitle = trim((string)($c['book_subtitle'] ?? ''));
    $sbSubmit   = trim((string)($c['book_submit'] ?? '')) ?: __('Submit');
    $sbMetrics  = ! array_key_exists('show_body_metrics', $c) || ! empty($c['show_body_metrics']);
    $sbCoachId  = (int) ($coach->id ?? ($page->coach_id ?? 0));
    $sbId       = 'csb-' . $sectionId;
@endphp
<section class="cs-schedule cs-pad {{ $carousel ? 'cs-schedule--carousel-m' : '' }}"
         @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['eyebrow']) || !empty($c['title']) || !empty($c['subtitle']))
            <div class="cs-section-head">
                @if(!empty($c['eyebrow']))<span class="cs-eyebrow">{{ $c['eyebrow'] }}</span>@endif
                @if(!empty($c['title']))<h2 class="cs-h2">{{ $c['title'] }}</h2>@endif
                @if(!empty($c['subtitle']))<p class="cs-lead">{{ $c['subtitle'] }}</p>@endif
            </div>
        @endif

        @if($items->isNotEmpty())
            <div class="cs-grid cs-grid--{{ $cols }} cs-schedule__grid">
                @foreach($items as $it)
                    @php
                        $time   = trim((string) ($it['time'] ?? ''));
                        $title  = trim((string) ($it['title'] ?? ''));
                        $coach  = trim((string) ($it['instructor'] ?? ''));
                        $desc   = trim((string) ($it['description'] ?? ''));
                        $bt     = trim((string) ($it['btn_text'] ?? ''));
                        $bu     = trim((string) ($it['btn_url'] ?? ''));
                        $badge  = trim((string) ($it['badge_color'] ?? ''));
                        $cardBg = trim((string) ($it['card_bg'] ?? ''));
                        $status = $it['status'] ?? 'none';
                        $status = in_array($status, ['upcoming','live','completed'], true) ? $status : '';
                    @endphp
                    <article class="cs-schedule__card" @if($cardBg !== '') style="background:{{ $cardBg }}" @endif>
                        <div class="cs-schedule__top">
                            @if($time !== '')<span class="cs-schedule__time">{{ $time }}</span>@endif
                            @if($coach !== '')
                                <span class="cs-schedule__coach" @if($badge !== '') style="background:{{ $badge }}" @endif>{{ $coach }}</span>
                            @endif
                        </div>

                        @if($status !== '')
                            <span class="cs-schedule__status cs-schedule__status--{{ $status }}">{{ __(ucfirst($status)) }}</span>
                        @endif

                        <div class="cs-schedule__head">
                            @if($title !== '')<h3 class="cs-schedule__title">{{ $title }}</h3>@endif
                            {{-- 2026-06-26 — "Book Now" opens the reusable Booking Enquiry
                                 modal in-page (no redirect) instead of an external link. The
                                 class context (title/time/instructor) rides along on the
                                 trigger so it's stored with the lead. btn_text is optional
                                 now (defaults to "Book Now"); btn_url is no longer required. --}}
                            @php $bookLabel = $bt !== '' ? $bt : __('Book Now'); @endphp
                            <button type="button"
                                    class="cs-btn cs-btn--primary cs-btn--sm cs-schedule__cta {{ $sbEnabled ? 'cs-sched-book' : 'cs-book-trigger' }}"
                                    @if($sbEnabled) data-sb="{{ $sbId }}" @endif
                                    data-source-page="{{ $c['title'] ?? __('Class Schedule') }}"
                                    data-source-button="{{ $bookLabel }}"
                                    data-class="{{ $title }}"
                                    data-time="{{ $time }}"
                                    data-instructor="{{ $coach }}"
                                    data-trainer="{{ $coach }}"
                                    data-schedule-id="{{ $title !== '' ? $title : $time }}">{{ $bookLabel }}</button>
                        </div>

                        @if($desc !== '')<p class="cs-schedule__focus">{{ $desc }}</p>@endif
                    </article>
                @endforeach
            </div>
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('No classes yet — add some in the editor.') }}</div>
        @endif
    </div>
</section>

@if($sbEnabled)
<style nonce="{{ csp_nonce() }}">
    .cs-sb-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);display:flex;align-items:flex-start;justify-content:center;padding:24px 14px;overflow:auto;z-index:9999}
    .cs-sb-ov[hidden]{display:none}
    .cs-sb-panel{width:100%;max-width:760px;background:#fff;border-radius:20px;padding:22px 26px 26px;position:relative;box-shadow:0 20px 50px -12px rgba(0,0,0,.4)}
    .cs-sb-x{position:absolute;top:14px;right:14px;width:34px;height:34px;border:none;border-radius:50%;background:#f97316;color:#fff;font-size:16px;cursor:pointer}
    .cs-sb-title{text-align:center;font-size:22px;font-weight:700;color:#0f172a;margin:2px 0 4px}
    .cs-sb-title span{color:#f97316}
    .cs-sb-sub{text-align:center;color:#64748b;font-size:13px;margin:0 0 14px}
    .cs-sb-summary{background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:12px 16px;margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:5px 20px;font-size:13.5px;color:#334155}
    .cs-sb-summary span{color:#64748b}
    .cs-sb-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 24px}
    .cs-sb-col{display:flex;flex-direction:column;gap:11px}
    .cs-sb-col label{display:block;font-size:13px;color:#334155}
    .cs-sb-col label > span{color:#ef4444}
    .cs-sb-col input,.cs-sb-col select,.cs-sb-col textarea{width:100%;box-sizing:border-box;margin-top:5px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:9px 12px;font-size:13.5px;color:#0f172a}
    .cs-sb-row{display:grid;grid-template-columns:1fr 1fr;gap:11px}
    .cs-sb-submit{margin-top:4px;border:none;color:#fff;background:linear-gradient(90deg,#fb7185,#f97316);border-radius:999px;padding:11px 26px;font-size:14px;font-weight:600;cursor:pointer}
    .cs-sb-msg{margin-top:8px;font-size:13px;border-radius:10px;padding:9px 12px}
    .cs-sb-msg.ok{background:#ecfdf5;color:#065f46}
    .cs-sb-msg.err{background:#fef2f2;color:#991b1b}
    @media(max-width:640px){.cs-sb-summary,.cs-sb-grid{grid-template-columns:1fr}}
</style>

<div class="cs-sb-ov" data-sb-modal="{{ $sbId }}" hidden aria-hidden="true" role="dialog" aria-modal="true">
    <div class="cs-sb-panel">
        <button type="button" class="cs-sb-x" data-sb-close aria-label="{{ __('Close') }}">&times;</button>
        @php $sbWords = preg_split('/\s+/', trim($sbTitle)); $sbLast = array_pop($sbWords); $sbFirst = implode(' ', $sbWords); @endphp
        <div class="cs-sb-title">{{ $sbFirst }} <span>{{ $sbLast }}</span></div>
        @if($sbSubtitle)<p class="cs-sb-sub">{{ $sbSubtitle }}</p>@endif

        <div class="cs-sb-summary">
            <div><span>{{ __('Class') }}:</span> <b data-sb-class>—</b></div>
            <div><span>{{ __('Trainer') }}:</span> <b data-sb-trainer>—</b></div>
            <div><span>{{ __('Slot time') }}:</span> <b data-sb-slot>—</b></div>
            <div><span>{{ __('Price') }}:</span> <b data-sb-price>—</b></div>
        </div>

        <form data-sb-form>
            <input type="hidden" name="coach_id" value="{{ $sbCoachId }}">
            <input type="hidden" name="section_id" value="{{ $sectionId }}">
            <input type="hidden" name="class_name" data-h="class_name">
            <input type="hidden" name="class_time" data-h="class_time">
            <input type="hidden" name="trainer" data-h="trainer">
            <input type="hidden" name="schedule_id" data-h="schedule_id">
            <input type="hidden" name="source_page" data-h="source_page">
            <input type="hidden" name="source_button" data-h="source_button">
            <input type="hidden" name="price" data-sb-priceval>
            <div class="cs-sb-grid">
                <div class="cs-sb-col">
                    @if($sbPlanTypes->isNotEmpty())
                    <label>{{ __('Plan Type') }}
                        <select name="plan_type">@foreach($sbPlanTypes as $pt)<option>{{ $pt }}</option>@endforeach</select>
                    </label>
                    @endif
                    @if($sbCourseTypes->isNotEmpty())
                    <label>{{ __('Course Type') }}
                        <select name="course_type">@foreach($sbCourseTypes as $ct)<option>{{ $ct }}</option>@endforeach</select>
                    </label>
                    @endif
                    <label>{{ __('Time Period') }} <span>*</span>
                        <select name="time_period" data-sb-period required>
                            <option value="">{{ __('Select') }}</option>
                            @foreach($sbPeriods as $p)
                                <option value="{{ $p['label'] }}" data-price="{{ $p['price'] }}">{{ $p['label'] }} &ndash; &#8377;{{ $p['price'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($sbMetrics)
                    <div class="cs-sb-row">
                        <label>{{ __('Height') }}<input name="height" placeholder="cm"></label>
                        <label>{{ __('Weight') }}<input name="weight" placeholder="kg"></label>
                    </div>
                    @endif
                </div>
                <div class="cs-sb-col">
                    <label>{{ __('Name') }} <span>*</span><input name="name" required></label>
                    <label>{{ __('Email') }} <span>*</span><input type="email" name="email" required></label>
                    <label>{{ __('Mobile') }} <span>*</span><input name="mobile" required></label>
                    @if($sbReasons->isNotEmpty())
                    <label>{{ __('Reason') }}
                        <select name="reason" data-sb-reason><option value="">{{ __('Select') }}</option>@foreach($sbReasons as $r)<option>{{ $r }}</option>@endforeach</select>
                    </label>
                    @endif
                    <label data-sb-problem hidden>{{ __('Problem Description') }}<textarea name="problem" rows="2" placeholder="{{ __('Optional') }}"></textarea></label>
                    <button type="submit" class="cs-sb-submit">{{ $sbSubmit }}</button>
                    <div class="cs-sb-msg" data-sb-msg hidden></div>
                </div>
            </div>
        </form>
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function(){
    var SBID = '{{ $sbId }}';
    var modal = document.querySelector('[data-sb-modal="'+SBID+'"]');
    if (!modal) return;
    var CSRF = (document.querySelector('meta[name="csrf-token"]')||{}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
    var f = modal.querySelector('[data-sb-form]');
    var msg = modal.querySelector('[data-sb-msg]');
    var priceEl = modal.querySelector('[data-sb-price]');
    var priceVal = modal.querySelector('[data-sb-priceval]');
    function set(sel, v){ var el = modal.querySelector('[data-h="'+sel+'"]'); if (el) el.value = v || ''; }
    function txt(sel, v){ var el = modal.querySelector('[data-sb-'+sel+']'); if (el) el.textContent = v || '—'; }
    function open(){ modal.hidden=false; modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
    function close(){ modal.hidden=true; modal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }

    document.addEventListener('click', function(e){
        var t = e.target.closest('.cs-sched-book[data-sb="'+SBID+'"]');
        if (t){
            set('class_name', t.getAttribute('data-class')); set('class_time', t.getAttribute('data-time'));
            set('trainer', t.getAttribute('data-trainer')); set('schedule_id', t.getAttribute('data-schedule-id'));
            set('source_page', t.getAttribute('data-source-page')); set('source_button', t.getAttribute('data-source-button'));
            txt('class', t.getAttribute('data-class')); txt('trainer', t.getAttribute('data-trainer')); txt('slot', t.getAttribute('data-time'));
            txt('price', '—'); if (priceVal) priceVal.value=''; if (msg){ msg.hidden=true; msg.textContent=''; }
            var ps = f.querySelector('[data-sb-period]'); if (ps) ps.value='';
            open(); return;
        }
        if (e.target.closest('[data-sb-close]') && e.target.closest('[data-sb-modal="'+SBID+'"]')) close();
    });
    document.addEventListener('keydown', function(e){ if (e.key==='Escape' && !modal.hidden) close(); });

    var period = f.querySelector('[data-sb-period]');
    if (period) period.addEventListener('change', function(){
        var o = this.options[this.selectedIndex]; var pr = o ? o.getAttribute('data-price') : '';
        if (priceVal) priceVal.value = pr || '';
        if (priceEl) priceEl.textContent = pr ? ('₹' + pr) : '—';
    });
    var reason = f.querySelector('[data-sb-reason]');
    var problem = f.querySelector('[data-sb-problem]');
    if (reason && problem) reason.addEventListener('change', function(){ problem.hidden = (this.value||'').toLowerCase() !== 'problem'; });

    function ensureRzp(cb){
        if (typeof Razorpay !== 'undefined'){ cb(true); return; }
        var s = document.getElementById('cs-rzp-checkout-js');
        if (!s){ s=document.createElement('script'); s.id='cs-rzp-checkout-js'; s.src='https://checkout.razorpay.com/v1/checkout.js'; document.head.appendChild(s); }
        var n=0, iv=setInterval(function(){ if (typeof Razorpay!=='undefined'){ clearInterval(iv); cb(true);} else if (++n>120){ clearInterval(iv); cb(false);} },100);
    }
    function post(url, obj){
        var fd = new FormData(); fd.set('_token', CSRF);
        Object.keys(obj).forEach(function(k){ fd.set(k, obj[k]); });
        return fetch(url,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.json().catch(function(){return {ok:false};});});
    }
    function show(cls, m){ if(!msg) return; msg.hidden=false; msg.className='cs-sb-msg '+cls; msg.textContent=m; }

    f.addEventListener('submit', function(e){
        e.preventDefault();
        var btn=f.querySelector('.cs-sb-submit'); var orig=btn.textContent; btn.disabled=true; btn.textContent='{{ __('Sending…') }}';
        var fd=new FormData(f); fd.set('_token',CSRF);
        fetch('{{ route('coach.schedule-booking') }}',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'})
          .then(function(r){return r.json().catch(function(){return {ok:false};});})
          .then(function(d){
            if (d && d.ok && d.mode==='payment'){ startPay(d); return; }
            if (d && d.ok){ f.reset(); show('ok', d.message||'{{ __('Thank you!') }}'); setTimeout(close,2200); }
            else { show('err', (d&&d.message)||'{{ __('Something went wrong. Please try again.') }}'); }
          })
          .catch(function(){ show('err','{{ __('Network error. Please try again.') }}'); })
          .finally(function(){ btn.disabled=false; btn.textContent=orig; });
    });

    function startPay(d){
        show('ok','{{ __('Opening secure payment…') }}');
        ensureRzp(function(ok){
            if(!ok){ show('err','{{ __('Payment library failed to load. Please try again.') }}'); return; }
            var opt={ key:d.key, order_id:d.order_id, amount:d.amount, currency:d.currency||'INR', name:d.name||document.title, description:d.description||'', prefill:d.prefill||{}, theme:{color:d.theme||'#f97316'},
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
@endif
