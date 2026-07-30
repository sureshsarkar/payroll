{{-- Pricing & Plans v1 — category cards. Booking mode (Online/Offline):
     Course Type → Time Period selectors → price → Book Class → adaptive modal
     (Individual / Couple) that posts a lead. CTA mode (Private/Corporate):
     direct redirect buttons. Self-contained JS (guarded, nonce'd); styling in
     coach-site.css (.cs-pricing*). White-label: lead attributed to this coach. --}}
@php
    $c        = $content;
    $cols     = (int) ($c['columns'] ?? 4); $cols = in_array($cols, [2,3,4], true) ? $cols : 4;
    $slots    = collect($c['time_slots'] ?? [])->map(fn($s)=>trim((string)$s))->filter()->values();
    $cats     = collect($c['categories'] ?? [])->filter(fn($cat)=>trim((string)($cat['name']??''))!=='')->values();
    $coachId  = (int) ($coach->id ?? ($page->coach_id ?? 0));
    $pid      = 'csp-' . $sectionId;
@endphp
<section class="cs-pricing cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['eyebrow']) || !empty($c['title']) || !empty($c['subtitle']))
            <div class="cs-section-head">
                @if(!empty($c['eyebrow']))<span class="cs-eyebrow">{{ $c['eyebrow'] }}</span>@endif
                @if(!empty($c['title']))<h2 class="cs-h2">{{ $c['title'] }}</h2>@endif
                @if(!empty($c['subtitle']))<p class="cs-lead">{{ $c['subtitle'] }}</p>@endif
            </div>
        @endif

        @if($cats->isNotEmpty())
            <div class="cs-grid cs-grid--{{ $cols }} cs-pricing__grid" id="{{ $pid }}" data-coach="{{ $coachId }}">
                @foreach($cats as $cat)
                    @php
                        $name   = trim((string) ($cat['name'] ?? ''));
                        $mode   = ($cat['mode'] ?? 'booking') === 'cta' ? 'cta' : 'booking';
                        $feats  = collect($cat['features'] ?? [])->map(fn($f)=>trim((string)$f))->filter()->values();
                        $indiv  = collect($cat['individual_periods'] ?? [])->map(fn($p)=>['label'=>trim((string)($p['label']??'')),'price'=>trim((string)($p['price']??''))])->filter(fn($p)=>$p['label']!=='')->values()->all();
                        $couple = collect($cat['couple_periods'] ?? [])->map(fn($p)=>['label'=>trim((string)($p['label']??'')),'price'=>trim((string)($p['price']??''))])->filter(fn($p)=>$p['label']!=='')->values()->all();
                        $buttons = collect($cat['cta_buttons'] ?? [])->filter(fn($b)=>trim((string)($b['label']??''))!=='')->values();
                        $firstPrice = $indiv[0]['price'] ?? ($couple[0]['price'] ?? '');
                    @endphp
                    <article class="cs-pricing__card cs-pricing__card--{{ $mode }}"
                             data-cat="{{ $name }}"
                             data-mode="{{ $mode }}"
                             data-individual='{{ json_encode($indiv, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) }}'
                             data-couple='{{ json_encode($couple, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) }}'>
                        <h3 class="cs-pricing__name">{{ $name }}</h3>

                        @if($mode === 'booking')
                            @if($firstPrice !== '')
                                <div class="cs-pricing__price"><span class="cs-pricing__cur">₹</span><span data-price>{{ $firstPrice }}</span></div>
                            @endif
                            <div class="cs-pricing__field">
                                <label>{{ __('Course Type') }}</label>
                                <select class="cs-pricing__select" data-ct>
                                    <option value="">{{ __('Choose Course Type') }}</option>
                                    @if(!empty($indiv))<option value="individual">{{ __('Individual Plan') }}</option>@endif
                                    @if(!empty($couple))<option value="couple">{{ __('Couple Plan') }}</option>@endif
                                </select>
                            </div>
                            <div class="cs-pricing__field" data-tp-wrap hidden>
                                <label>{{ __('Time Period') }}</label>
                                <select class="cs-pricing__select" data-tp>
                                    <option value="">{{ __('Choose Time Period') }}</option>
                                </select>
                            </div>
                            <button type="button" class="cs-btn cs-btn--primary cs-pricing__book" data-book disabled>{{ __('Book Class') }}</button>
                        @else
                            <div class="cs-pricing__cta">
                                @foreach($buttons as $b)
                                    <a class="cs-btn cs-btn--primary cs-pricing__cta-btn" href="{{ safe_url(trim((string)($b['url'] ?? ''))) }}"{{-- F6 --}}
                                       @if(\Illuminate\Support\Str::startsWith(trim((string)($b['url']??'')), ['http://','https://'])) target="_blank" rel="noopener" @endif>{{ $b['label'] }}</a>
                                @endforeach
                            </div>
                        @endif

                        @if($feats->isNotEmpty())
                            <ul class="cs-pricing__features">
                                @foreach($feats as $f)
                                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ $f }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('No pricing plans yet — add some in the editor.') }}</div>
        @endif
    </div>
</section>

@if($cats->isNotEmpty())
{{-- Shared booking modal --}}
<div class="cs-pricing-modal" data-pricing-modal="{{ $pid }}" hidden aria-hidden="true" role="dialog" aria-modal="true">
    <div class="cs-pricing-modal__overlay" data-close></div>
    <div class="cs-pricing-modal__box">
        <button type="button" class="cs-pricing-modal__close" data-close aria-label="{{ __('Close') }}">&times;</button>
        <h3 class="cs-pricing-modal__title">{{ __('Book a') }} <span>{{ __('session') }}</span></h3>

        <div class="cs-pricing-modal__summary">
            <div><b>{{ __('Plan') }}:</b> <span data-sum-cat></span></div>
            <div><b>{{ __('Course Type') }}:</b> <span data-sum-ct></span></div>
            <div><b>{{ __('Time Period') }}:</b> <span data-sum-tp></span></div>
            <div><b>{{ __('Price') }}:</b> ₹<span data-sum-price></span></div>
        </div>

        <form class="cs-pricing-modal__form" data-pricing-form action="{{ route('coach.pricing-enquiry') }}" method="POST">
            @csrf
            <input type="hidden" name="coach_id" value="{{ $coachId }}">
            <input type="hidden" name="section_id" value="{{ $sectionId }}">
            <input type="hidden" name="category" data-f="category">
            <input type="hidden" name="course_type" data-f="course_type">
            <input type="hidden" name="time_period" data-f="time_period">
            <input type="hidden" name="price" data-f="price">

            <div class="cs-pricing-modal__grid">
                <div class="cs-pricing-modal__col">
                    <div class="cs-pm-field">
                        <label>{{ __('Select Time Slot') }} <span class="req">*</span></label>
                        <select name="time_slot" required>
                            <option value="">{{ __('Choose Time Slot') }}</option>
                            @foreach($slots as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                        </select>
                    </div>

                    {{-- Couple: Person 1 + Person 2 --}}
                    <div data-couple-only hidden>
                        <div class="cs-pm-sub">{{ __('Person 1') }}</div>
                        <div class="cs-pm-field"><label>{{ __('Name') }}</label><input type="text" data-p1name placeholder="{{ __('Person 1 name') }}"></div>
                        <div class="cs-pm-row">
                            <div class="cs-pm-field"><label>{{ __('Age') }}</label><input type="text" name="age" inputmode="numeric"></div>
                            <div class="cs-pm-field"><label>{{ __('Gender') }}</label>
                                <select name="gender"><option value="">{{ __('Select') }}</option><option>Male</option><option>Female</option></select>
                            </div>
                        </div>
                        <div class="cs-pm-sub">{{ __('Person 2') }}</div>
                        <div class="cs-pm-field"><label>{{ __('Name') }}</label><input type="text" name="p2_name" placeholder="{{ __('Person 2 name') }}"></div>
                        <div class="cs-pm-row">
                            <div class="cs-pm-field"><label>{{ __('Age') }}</label><input type="text" name="p2_age" inputmode="numeric"></div>
                            <div class="cs-pm-field"><label>{{ __('Gender') }}</label>
                                <select name="p2_gender"><option value="">{{ __('Select') }}</option><option>Male</option><option>Female</option></select>
                            </div>
                        </div>
                    </div>

                    {{-- Individual-only extras --}}
                    <div data-individual-only>
                        <div class="cs-pm-row">
                            <div class="cs-pm-field"><label>{{ __('Age') }}</label><input type="text" name="i_age" inputmode="numeric"></div>
                            <div class="cs-pm-field"><label>{{ __('Gender') }}</label>
                                <select name="i_gender"><option value="">{{ __('Select') }}</option><option>Male</option><option>Female</option></select>
                            </div>
                        </div>
                        <div class="cs-pm-field"><label>{{ __('Reason') }}</label>
                            <select name="reason"><option value="">{{ __('Choose Reason') }}</option><option>Fitness</option><option>Health Problem</option><option>Other</option></select>
                        </div>
                        <div class="cs-pm-field"><label>{{ __('Problem Description') }}</label><textarea name="problem" rows="2" placeholder="{{ __('Optional') }}"></textarea></div>
                        <div class="cs-pm-row">
                            <div class="cs-pm-field"><label>{{ __('Height') }}</label><input type="text" name="height" placeholder="cm"></div>
                            <div class="cs-pm-field"><label>{{ __('Weight') }}</label><input type="text" name="weight" placeholder="kg"></div>
                        </div>
                    </div>
                </div>

                <div class="cs-pricing-modal__col">
                    <div class="cs-pm-field"><label>{{ __('Name') }} <span class="req">*</span></label><input type="text" name="name" required placeholder="{{ __('Enter Name') }}"></div>
                    <div class="cs-pm-field"><label>{{ __('Email') }} <span class="req">*</span></label><input type="email" name="email" required placeholder="{{ __('Enter Email') }}"></div>
                    <div class="cs-pm-field"><label>{{ __('Mobile') }} <span class="req">*</span></label><input type="text" name="mobile" required placeholder="{{ __('Enter Mobile') }}"></div>
                    {{-- Couple shares Reason here (kept simple) --}}
                    <button type="submit" class="cs-btn cs-btn--primary cs-pricing-modal__submit">{{ __('Submit') }}</button>
                    <div class="cs-pricing-modal__msg" data-msg hidden></div>
                </div>
            </div>
        </form>
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function(){
    var root = document.getElementById('{{ $pid }}');
    var modal = document.querySelector('[data-pricing-modal="{{ $pid }}"]');
    if (!root || !modal) return;
    var CSRF = document.querySelector('meta[name="csrf-token"]');
    CSRF = CSRF ? CSRF.getAttribute('content') : '';

    function parse(el, key){ try { return JSON.parse(el.getAttribute(key) || '[]'); } catch(e){ return []; } }

    // ---- card selectors ----
    root.querySelectorAll('.cs-pricing__card--booking').forEach(function(card){
        var ctSel = card.querySelector('[data-ct]');
        var tpWrap = card.querySelector('[data-tp-wrap]');
        var tpSel = card.querySelector('[data-tp]');
        var priceEl = card.querySelector('[data-price]');
        var bookBtn = card.querySelector('[data-book]');
        if (!ctSel) return;

        ctSel.addEventListener('change', function(){
            var type = ctSel.value;
            var periods = type === 'couple' ? parse(card,'data-couple') : (type === 'individual' ? parse(card,'data-individual') : []);
            tpSel.innerHTML = '<option value="">{{ __('Choose Time Period') }}</option>';
            periods.forEach(function(p){
                var o = document.createElement('option');
                o.value = p.label + '|' + p.price; o.textContent = p.label + (p.price ? ' – ₹' + p.price : '');
                tpSel.appendChild(o);
            });
            tpWrap.hidden = !type;
            bookBtn.disabled = true;
        });
        tpSel.addEventListener('change', function(){
            var v = tpSel.value;
            if (!v) { bookBtn.disabled = true; return; }
            var price = v.split('|')[1] || '';
            if (priceEl && price) priceEl.textContent = price;
            bookBtn.disabled = false;
        });
        bookBtn.addEventListener('click', function(){
            var type = ctSel.value, v = tpSel.value;
            if (!type || !v) return;
            var label = v.split('|')[0], price = v.split('|')[1] || '';
            openModal(card.getAttribute('data-cat'), type, label, price);
        });
    });

    // ---- modal ----
    var f = modal.querySelector('[data-pricing-form]');
    var msg = modal.querySelector('[data-msg]');
    function setF(name, val){ var el = modal.querySelector('[data-f="'+name+'"]'); if (el) el.value = val; }
    function openModal(cat, type, period, price){
        setF('category', cat); setF('course_type', type); setF('time_period', period); setF('price', price);
        modal.querySelector('[data-sum-cat]').textContent = cat;
        modal.querySelector('[data-sum-ct]').textContent = type === 'couple' ? '{{ __('Couple Plan') }}' : '{{ __('Individual Plan') }}';
        modal.querySelector('[data-sum-tp]').textContent = period;
        modal.querySelector('[data-sum-price]').textContent = price;
        var isCouple = type === 'couple';
        modal.querySelector('[data-couple-only]').hidden = !isCouple;
        modal.querySelector('[data-individual-only]').hidden = isCouple;
        if (msg) { msg.hidden = true; msg.textContent = ''; }
        modal.hidden = false; modal.setAttribute('aria-hidden','false');
        document.body.style.overflow = 'hidden';
    }
    function close(){ modal.hidden = true; modal.setAttribute('aria-hidden','true'); document.body.style.overflow = ''; }
    modal.querySelectorAll('[data-close]').forEach(function(b){ b.addEventListener('click', close); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && !modal.hidden) close(); });

    f.addEventListener('submit', function(e){
        e.preventDefault();
        var isCouple = !modal.querySelector('[data-couple-only]').hidden;
        var fd = new FormData(f);
        // Map mode-specific fields to the canonical names the backend expects.
        if (isCouple) {
            fd.set('name', (modal.querySelector('[data-p1name]') || {}).value || fd.get('name'));
            // person1 age/gender already named age/gender inside couple block
        } else {
            fd.set('age', fd.get('i_age') || ''); fd.set('gender', fd.get('i_gender') || '');
        }
        fd.delete('i_age'); fd.delete('i_gender');
        fd.set('_token', CSRF);
        var btn = f.querySelector('[type=submit]'); var orig = btn.textContent;
        btn.disabled = true; btn.textContent = '{{ __('Sending…') }}';
        fetch(f.getAttribute('action'), { method:'POST', body: fd, headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }, credentials:'same-origin' })
            .then(function(r){ return r.json().catch(function(){ return {ok:false}; }); })
            .then(function(d){
                // Payment mode — the coach collects payment for this priced plan.
                // The enquiry is already saved (unpaid); open Razorpay for the
                // server-created order. Amount is fixed by the server order.
                if (d && d.ok && d.mode === 'payment') {
                    startPay(d);
                    return;
                }
                if (d && d.ok) {
                    f.reset();
                    if (msg) { msg.hidden = false; msg.className = 'cs-pricing-modal__msg is-ok'; msg.textContent = d.message || 'Thank you!'; }
                    setTimeout(close, 2200);
                } else {
                    if (msg) { msg.hidden = false; msg.className = 'cs-pricing-modal__msg is-err'; msg.textContent = (d && d.message) || 'Something went wrong. Please try again.'; }
                }
            })
            .catch(function(){ if (msg) { msg.hidden = false; msg.className = 'cs-pricing-modal__msg is-err'; msg.textContent = 'Network error. Please try again.'; } })
            .finally(function(){ btn.disabled = false; btn.textContent = orig; });
    });

    /* ── Razorpay checkout bridge (mirrors the trial popup). ─────────────
       Loads checkout.js on demand (strict-dynamic trusts this nonce'd script's
       injected child). On success POSTs the signed result to the verify
       endpoint; on dismiss POSTs a cancel so the enquiry is flagged cancelled
       (the enquiry is always kept). The amount is fixed by the server order. */
    function ensureRzp(cb){
        if (typeof Razorpay !== 'undefined') { cb(true); return; }
        var s = document.getElementById('cs-rzp-checkout-js');
        if (!s) {
            s = document.createElement('script');
            s.id = 'cs-rzp-checkout-js';
            s.src = 'https://checkout.razorpay.com/v1/checkout.js';
            document.head.appendChild(s);
        }
        var tries = 0, iv = setInterval(function(){
            if (typeof Razorpay !== 'undefined') { clearInterval(iv); cb(true); }
            else if (++tries > 120) { clearInterval(iv); cb(false); }
        }, 100);
    }

    function postForm(url, fields){
        var fd = new FormData();
        fd.set('_token', CSRF);
        Object.keys(fields).forEach(function(k){ fd.set(k, fields[k]); });
        return fetch(url, { method:'POST', body: fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }, credentials:'same-origin' })
            .then(function(r){ return r.json().catch(function(){ return {ok:false}; }); });
    }

    // Redirect back to THIS coach page carrying the outcome so the master layout
    // flashes the Thank-You / Failed / Cancelled banner. Thank-You is shown ONLY
    // after a verified successful payment (never before).
    function outcomeUrl(status){
        var base = location.href.split('#')[0].split('?')[0];
        return base + '?booking=' + status;
    }
    function goTo(status){ window.location.href = outcomeUrl(status); }

    function startPay(d){
        if (msg) { msg.hidden = false; msg.className = 'cs-pricing-modal__msg is-ok'; msg.textContent = '{{ __('Opening secure payment…') }}'; }
        ensureRzp(function(ok){
            if (!ok) { if (msg) { msg.className = 'cs-pricing-modal__msg is-err'; msg.textContent = '{{ __('Payment library failed to load. Please try again.') }}'; } return; }
            var settled = false;
            var options = {
                key: d.key, order_id: d.order_id, amount: d.amount, currency: d.currency || 'INR',
                name: d.name || document.title, description: d.description || '',
                prefill: d.prefill || {}, theme: { color: d.theme || '#111827' },
                modal: { ondismiss: function(){
                    // Visitor closed the gateway → cancelled. Keep the enquiry, flag
                    // it, then send them back to the pricing page with a message.
                    if (settled) { return; }
                    settled = true;
                    postForm(d.cancel_url, { payment_id: d.payment_id }).finally(function(){ goTo('cancelled'); });
                } },
                handler: function(resp){
                    settled = true;
                    postForm(d.verify_url, {
                        payment_id: d.payment_id,
                        razorpay_payment_id: resp.razorpay_payment_id,
                        razorpay_order_id: resp.razorpay_order_id,
                        razorpay_signature: resp.razorpay_signature
                    }).then(function(j){
                        goTo(j && j.ok ? 'success' : 'failed');   // success → Thank-You, else → Failed
                    }).catch(function(){ goTo('failed'); });
                }
            };
            try { (new Razorpay(options)).open(); }
            catch(e){ if (msg) { msg.className = 'cs-pricing-modal__msg is-err'; msg.textContent = '{{ __('Could not open payment window.') }}'; } }
        });
    }
})();
</script>
@endif
