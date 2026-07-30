{{-- Public Trainer Detail Page (2026-07-15, Phase 1).
     $trainer (CoachTrainer), $packages (Collection<TrainerSessionPackage>),
     $coach (User), $brand, $site. Self-contained styling (.td-*), brand-aware
     via var(--brand-primary). The "Book" buttons use the shared .cs-book-trigger
     modal today; Phase 2 upgrades them to the dedicated payment popup. --}}
@php
    $trName    = $trainer->name;
    $initials  = collect(explode(' ', trim($trName)))->filter()->map(fn($w)=>mb_substr($w,0,1))->take(2)->implode('');
    $photoUrl  = $trainer->photo ? \Illuminate\Support\Facades\Storage::url($trainer->photo) : null;
    $tags      = is_array($trainer->tags) ? array_filter($trainer->tags) : [];
    $homeUrl   = url('/');
    $curSym    = fn($c) => ($c ?: 'INR') === 'INR' ? '₹' : (($c ?: 'INR') . ' ');
@endphp

{{-- Full-width gradient hero banner (matches the coach's design doc). Brand-aware. --}}
<div class="td-hero">
    <div class="cs-container">
        <h1 class="td-hero__title">{{ __('Trainer Detail Page') }}</h1>
    </div>
</div>

<section class="td">
    <div class="cs-container">

        {{-- Breadcrumb --}}
        <nav class="td__crumbs" aria-label="Breadcrumb">
            <a href="{{ $homeUrl }}">{{ __('Home') }}</a>
            <span class="td__sep">/</span>
            <span class="td__crumb-current">{{ \Illuminate\Support\Str::limit($trName, 48) }}</span>
        </nav>

        @if($trainer && ! $trainer->is_active)
            <div class="td__preview-flag">{{ __('Preview — this trainer is hidden from visitors. Toggle "Active" to publish.') }}</div>
        @endif

        <div class="td__wrap">
            {{-- Photo --}}
            <div class="td__photo">
                @if($photoUrl)<img src="{{ $photoUrl }}" alt="{{ $trName }}" loading="eager">@else<span>{{ $initials ?: 'T' }}</span>@endif
            </div>

            {{-- About / COACH DETAIL block --}}
            <div class="td__about">
                <h2 class="td__name">{{ __('About') }} {{ $trName }}</h2>
                <span class="td__badge">{{ __('COACH DETAIL') }}</span>

                @if($trainer->certificate_date || $trainer->certificate_number)
                    <div class="td__cert">
                        @if($trainer->certificate_date){{ __('Certificate issue date') }}: {{ $trainer->certificate_date }}@endif
                        @if($trainer->certificate_date && $trainer->certificate_number) · @endif
                        @if($trainer->certificate_number){{ __('Certificate Number') }}: {{ $trainer->certificate_number }}@endif
                    </div>
                @endif

                @if($trainer->specialisation || $trainer->experience)
                    <div class="td__facts">
                        @if($trainer->specialisation)<span class="td__fact"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ $trainer->specialisation }}</span>@endif
                        @if($trainer->experience)<span class="td__fact"><i class="fa-solid fa-medal" aria-hidden="true"></i> {{ $trainer->experience }}</span>@endif
                    </div>
                @endif

                @if($trainer->bio)
                    <p class="td__bio">{!! nl2br(e($trainer->bio)) !!}</p>
                @endif

                @if($tags)
                    <div class="td__tags">
                        @foreach($tags as $tag)<span class="td__tag">{{ $tag }}</span>@endforeach
                    </div>
                @endif

                @if($packages->count())
                    <ul class="td__list">
                        @foreach($packages as $p)
                            <li><span class="td__tick"><i class="fa-solid fa-check" aria-hidden="true"></i></span> {{ $p->sessionLabel() }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="td__cta td-book" data-package-id="{{ optional($packages->first())->id }}">
                        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> {{ __('Book Now') }}
                    </button>
                    <p class="td__note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> {{ __('Secure booking. Our team confirms your schedule after you submit.') }}</p>
                @else
                    <div class="td__empty">{{ __('Session packages will be available soon. Please check back later.') }}</div>
                @endif
            </div>
        </div>

    </div>
</section>

<style nonce="{{ csp_nonce() }}">
    .td-hero{background:var(--brand-primary,#e2701e);
        background:linear-gradient(120deg, var(--brand-primary,#e2701e) 0%, color-mix(in srgb, var(--brand-primary,#e2701e) 60%, #ec4899) 100%);
        padding:56px 0;text-align:center;margin-bottom:30px;}
    .td-hero__title{margin:0;color:#fff;font-size:40px;font-weight:800;letter-spacing:-.02em;line-height:1.1;}
    @media (max-width:640px){.td-hero{padding:38px 0;}.td-hero__title{font-size:28px;}}
    .td{padding:26px 0 60px;}
    .td__crumbs{font-size:13px;color:#94a3b8;margin-bottom:18px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    .td__crumbs a{color:var(--brand-primary,#6366F1);text-decoration:none;}
    .td__sep{color:#cbd5e1;}
    .td__preview-flag{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;margin-bottom:16px;}
    .td__wrap{display:grid;grid-template-columns:minmax(260px,420px) 1fr;gap:40px;align-items:start;}
    .td__photo{border-radius:18px;overflow:hidden;background:var(--brand-primary,#6366F1);color:#fff;aspect-ratio:4/5;
        display:flex;align-items:center;justify-content:center;font-size:46px;font-weight:800;box-shadow:0 20px 44px -24px rgba(15,23,42,.45);}
    .td__photo img{width:100%;height:100%;object-fit:cover;}
    .td__about{min-width:0;}
    .td__name{margin:0 0 12px;font-size:32px;font-weight:800;letter-spacing:-.02em;color:#0f172a;line-height:1.12;}
    .td__badge{display:inline-block;background:color-mix(in srgb, var(--brand-primary,#6366F1) 14%, #fff);color:var(--brand-primary,#6366F1);
        font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;padding:5px 13px;border-radius:999px;margin-bottom:12px;}
    .td__cert{font-size:13px;color:#64748b;margin-bottom:12px;}
    .td__facts{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;}
    .td__fact{font-size:13.5px;color:#475569;font-weight:600;display:inline-flex;align-items:center;gap:6px;}
    .td__fact i{color:var(--brand-primary,#6366F1);}
    .td__bio{font-size:14.5px;line-height:1.7;color:#334155;margin:0 0 16px;}
    .td__tags{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px;}
    .td__tag{background:#f1f5f9;color:#475569;font-size:12px;font-weight:600;padding:4px 11px;border-radius:999px;}
    .td__list{list-style:none;margin:0 0 20px;padding:0;display:flex;flex-direction:column;gap:11px;}
    .td__list li{display:flex;align-items:center;gap:11px;font-size:14.5px;color:#1e293b;font-weight:500;}
    .td__tick{width:20px;height:20px;flex:none;border-radius:50%;background:var(--brand-primary,#6366F1);color:#fff;
        display:inline-flex;align-items:center;justify-content:center;font-size:11px;}
    .td__cta{border:none;border-radius:12px;padding:13px 26px;font-size:14.5px;font-weight:700;color:#fff;cursor:pointer;
        background:var(--brand-primary,#6366F1);box-shadow:0 12px 26px -12px rgba(99,102,241,.7);
        display:inline-flex;align-items:center;gap:9px;transition:transform .14s;}
    .td__cta:hover{transform:translateY(-1px);}
    .td__note{margin:14px 0 0;font-size:12.5px;color:#94a3b8;display:flex;align-items:center;gap:7px;}
    .td__empty{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:14px;padding:28px;text-align:center;color:#94a3b8;font-size:14px;}
    @media (max-width:820px){
        .td__wrap{grid-template-columns:1fr;gap:22px;}
        .td__photo{max-width:340px;aspect-ratio:4/4;}
        .td__name{font-size:26px;}
    }
</style>

{{-- Booking modal + payment — shared partial (also used by the trainer_booking_v1 builder section). --}}
@if($packages->count())
    @php
        $bkSym = fn($c) => ($c ?: 'INR') === 'INR' ? '₹' : (($c ?: 'INR') . ' ');
    @endphp
    @include('frontend.coach-site.partials._trainer-booking-modal', [
        'bkTitle'       => $trainer->name,
        'bkCoachId'     => (isset($coach) && !empty($coach->id)) ? (int) $coach->id : (int) $trainer->coach_id,
        'bkHidden'      => ['trainer_id' => $trainer->id],
        'bkPlanTypes'   => $trainer->planTypeOptions(),
        'bkCourseTypes' => $trainer->courseTypeOptions(),
        'bkReasons'     => $trainer->reasonOptions(),
        'bkPackages'    => $packages->map(fn ($p) => [
            'value' => $p->id, 'label' => $p->sessionLabel(), 'price' => (float) $p->price, 'sym' => $bkSym($p->currency),
        ])->all(),
    ])
@endif
