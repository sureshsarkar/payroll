{{-- Trainer Booking section v1 (2026-07-15, self-contained). Drop onto ANY
     builder page (e.g. a hand-built trainer/About page) to add a working "Book
     Personal Class Session" button + form + payment — WITHOUT a Coach-Panel
     trainer entity. The coach configures the trainer name + packages (label +
     PRICE) right here; the server re-reads the price from THIS section
     (section_id + tenant gate) so the amount can never be tampered. Same proven
     pattern as Pricing & Plans / Classes & Schedules. Receives $content, $coach,
     $sectionId, $brand, $isOwnerPreview. --}}
@php
    $c        = $content;
    $trName   = trim((string) ($c['trainer_name'] ?? ''));
    $heading  = trim((string) ($c['heading'] ?? ''));
    $intro    = trim((string) ($c['intro'] ?? ''));
    $btnText  = trim((string) ($c['button_text'] ?? '')) ?: __('Book Now');
    $showPkgs = ! empty($c['show_packages']);
    $curLabel = trim((string) ($c['currency'] ?? '')) ?: 'INR';
    $sym      = $curLabel === 'INR' ? '₹' : ($curLabel . ' ');

    // Only packages with a numeric price > 0 are bookable (price drives payment).
    $rawPkgs  = array_values((array) ($c['packages'] ?? []));
    $bkPkgs   = [];
    foreach ($rawPkgs as $i => $p) {
        $label = trim((string) (is_array($p) ? ($p['label'] ?? '') : ''));
        $price = (float) preg_replace('/[^0-9.]/', '', (string) (is_array($p) ? ($p['price'] ?? '') : ''));
        if ($label === '') { continue; }
        $bkPkgs[] = ['value' => $i, 'label' => $label, 'price' => $price, 'sym' => $sym];
    }
    $hasBookable = collect($bkPkgs)->contains(fn ($p) => $p['price'] > 0);
@endphp

@if($trName !== '' && count($bkPkgs))
<section class="tbook" style="{{ $appearanceStyle ?? '' }}">
    <div class="cs-container">
        @if($heading || $intro)
            <div class="tbook__head">
                @if($heading)<h2 class="tbook__title">{{ $heading }}</h2>@endif
                @if($intro)<p class="tbook__intro">{{ $intro }}</p>@endif
            </div>
        @endif

        @if($showPkgs)
            <ul class="tbook__list">
                @foreach($bkPkgs as $p)
                    <li><span class="tbook__tick"><i class="fa-solid fa-check" aria-hidden="true"></i></span> {{ $p['label'] }}@if($p['price'] > 0) – {{ $sym }}{{ number_format($p['price'], 0) }}@endif</li>
                @endforeach
            </ul>
        @endif

        <button type="button" class="tbook__btn td-book" data-package-id="{{ $bkPkgs[0]['value'] }}">
            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> {{ $btnText }}
        </button>
        <p class="tbook__note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> {{ __('Secure booking · amount verified on our server.') }}</p>
    </div>
</section>

@include('frontend.coach-site.partials._trainer-booking-modal', [
    'bkTitle'       => $trName,
    'bkCoachId'     => (isset($coach) && !empty($coach->id)) ? (int) $coach->id : 0,
    'bkHidden'      => ['section_id' => (int) ($sectionId ?? 0)],
    'bkPlanTypes'   => (array) ($c['plan_types'] ?? []),
    'bkCourseTypes' => (array) ($c['course_types'] ?? []),
    'bkReasons'     => (array) ($c['reasons'] ?? []),
    'bkPackages'    => $bkPkgs,
])

<style nonce="{{ csp_nonce() }}">
    .tbook{padding:30px 0;}
    .tbook__head{margin-bottom:16px;}
    .tbook__title{font-size:22px;font-weight:800;color:#0f172a;margin:0 0 6px;letter-spacing:-.01em;}
    .tbook__intro{font-size:14.5px;color:#64748b;margin:0;line-height:1.6;}
    .tbook__list{list-style:none;margin:0 0 18px;padding:0;display:flex;flex-direction:column;gap:11px;}
    .tbook__list li{display:flex;align-items:center;gap:11px;font-size:14.5px;color:#1e293b;font-weight:500;}
    .tbook__tick{width:20px;height:20px;flex:none;border-radius:50%;background:var(--brand-primary,#6366F1);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;}
    .tbook__btn{border:none;border-radius:12px;padding:13px 28px;font-size:15px;font-weight:700;color:#fff;cursor:pointer;
        background:var(--brand-primary,#6366F1);box-shadow:0 12px 26px -12px rgba(99,102,241,.7);display:inline-flex;align-items:center;gap:9px;transition:transform .14s;}
    .tbook__btn:hover{transform:translateY(-1px);}
    .tbook__note{margin:12px 0 0;font-size:12.5px;color:#94a3b8;display:flex;align-items:center;gap:7px;}
</style>
@elseif(!empty($isOwnerPreview))
<section class="tbook"><div class="cs-container">
    <div style="background:#fffbeb;border:1px dashed #fde68a;border-radius:12px;padding:22px;color:#92400e;font-size:13.5px;">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        {{ __('Trainer Booking: set the trainer name + at least one package (label + price) in this section\'s settings. The price you enter here is what the server charges.') }}
    </div>
</div></section>
@endif
