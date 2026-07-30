{{-- Trainers section v1 (2026-07-15). A grid of the coach's trainer profiles,
     pulled LIVE + tenant-scoped so profile/package edits reflect automatically.
     Each card links to /trainers/{slug} (the public Trainer Detail Page) where
     the visitor books a Personal Class Session. Self-contained styling (.tsec-*),
     brand-aware via var(--brand-primary). Receives $content, $coach, $brand. --}}
@php
    $c        = $content;
    $eyebrow  = trim((string) ($c['eyebrow'] ?? ''));
    $title    = trim((string) ($c['title'] ?? ''));
    $subtitle = trim((string) ($c['subtitle'] ?? ''));
    $cols     = (int) ($c['columns'] ?? 3); $cols = in_array($cols, [2, 3, 4], true) ? $cols : 3;
    $limit    = (int) ($c['limit'] ?? 0);
    $btnText  = trim((string) ($c['btn_text'] ?? '')) ?: __('View Profile');
    $showSpec = ! array_key_exists('show_specialisation', $c) || ! empty($c['show_specialisation']);
    $showExp  = ! array_key_exists('show_experience', $c) || ! empty($c['show_experience']);
    $showPkg  = ! array_key_exists('show_packages_count', $c) || ! empty($c['show_packages_count']);

    // Tenant-safe live fetch — only THIS coach's active trainers, ordered.
    $trainers = collect();
    if (isset($coach) && ! empty($coach->id)) {
        $q = \App\Models\CoachTrainer::forCoach((int) $coach->id)->active()
            ->withCount(['packages' => fn ($x) => $x->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('id');
        if ($limit > 0) {
            $q->limit($limit);
        }
        $trainers = $q->get();
    }
@endphp

@if($trainers->isNotEmpty())
<section class="tsec" style="{{ $appearanceStyle ?? '' }}">
    <div class="cs-container">
        @if($eyebrow || $title || $subtitle)
            <div class="tsec__head">
                @if($eyebrow)<span class="tsec__eyebrow">{{ $eyebrow }}</span>@endif
                @if($title)<h2 class="tsec__title">{{ $title }}</h2>@endif
                @if($subtitle)<p class="tsec__sub">{{ $subtitle }}</p>@endif
            </div>
        @endif

        <div class="tsec__grid" style="--tsec-cols:{{ $cols }};">
            @foreach($trainers as $t)
                @php
                    $photo    = $t->photo ? \Illuminate\Support\Facades\Storage::url($t->photo) : null;
                    $initials = collect(explode(' ', trim($t->name)))->filter()->map(fn($w)=>mb_substr($w,0,1))->take(2)->implode('');
                    $url      = url('/trainers/' . $t->slug);
                @endphp
                <a class="tsec__card" href="{{ $url }}">
                    <div class="tsec__photo">
                        @if($photo)<img src="{{ $photo }}" alt="{{ $t->name }}" loading="lazy">@else<span>{{ $initials ?: 'T' }}</span>@endif
                    </div>
                    <div class="tsec__body">
                        <div class="tsec__name">{{ $t->name }}</div>
                        @if($showSpec && $t->specialisation)<div class="tsec__spec">{{ $t->specialisation }}</div>@endif
                        <div class="tsec__facts">
                            @if($showExp && $t->experience)<span><i class="fa-solid fa-medal" aria-hidden="true"></i> {{ $t->experience }}</span>@endif
                            @if($showPkg && $t->packages_count)<span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ $t->packages_count }} {{ __('packages') }}</span>@endif
                        </div>
                        <span class="tsec__btn">{{ $btnText }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

<style nonce="{{ csp_nonce() }}">
    .tsec{padding:52px 0;}
    .tsec__head{text-align:center;max-width:640px;margin:0 auto 32px;}
    .tsec__eyebrow{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--brand-primary,#6366F1);margin-bottom:8px;}
    .tsec__title{margin:0 0 8px;font-size:29px;font-weight:800;letter-spacing:-.02em;color:#0f172a;}
    .tsec__sub{margin:0;font-size:15px;color:#64748b;line-height:1.6;}
    .tsec__grid{display:grid;grid-template-columns:repeat(var(--tsec-cols,3),1fr);gap:18px;}
    @media (max-width:900px){.tsec__grid{grid-template-columns:repeat(2,1fr);}}
    @media (max-width:560px){.tsec__grid{grid-template-columns:1fr;}}
    .tsec__card{display:flex;flex-direction:column;background:#fff;border:1px solid #eef0f5;border-radius:18px;overflow:hidden;
        text-decoration:none;box-shadow:0 14px 34px -24px rgba(15,23,42,.35);transition:transform .16s,box-shadow .16s,border-color .16s;}
    .tsec__card:hover{transform:translateY(-4px);box-shadow:0 24px 46px -22px rgba(99,102,241,.45);border-color:var(--brand-primary,#6366F1);}
    .tsec__photo{aspect-ratio:4/3;background:var(--brand-primary,#6366F1);color:#fff;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;}
    .tsec__photo img{width:100%;height:100%;object-fit:cover;}
    .tsec__body{padding:16px 18px 18px;display:flex;flex-direction:column;gap:6px;flex:1;}
    .tsec__name{font-size:17px;font-weight:800;color:#0f172a;letter-spacing:-.01em;}
    .tsec__spec{font-size:13px;color:#64748b;}
    .tsec__facts{display:flex;gap:14px;flex-wrap:wrap;margin-top:2px;}
    .tsec__facts span{font-size:12.5px;color:#475569;font-weight:600;display:inline-flex;align-items:center;gap:5px;}
    .tsec__facts i{color:var(--brand-primary,#6366F1);}
    .tsec__btn{margin-top:auto;padding-top:12px;font-size:13.5px;font-weight:700;color:var(--brand-primary,#6366F1);display:inline-flex;align-items:center;gap:7px;}
    .tsec__card:hover .tsec__btn i{transform:translateX(3px);}
    .tsec__btn i{transition:transform .16s;}
</style>
@elseif(!empty($isOwnerPreview))
<section class="tsec" style="{{ $appearanceStyle ?? '' }}">
    <div class="cs-container">
        <div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:14px;padding:34px;text-align:center;color:#94a3b8;font-size:14px;">
            <i class="fa-solid fa-user-tie" style="font-size:26px;display:block;margin-bottom:10px;"></i>
            {{ __('No active trainers yet. Add trainers under Coach Panel → Trainers, then they appear here automatically.') }}
        </div>
    </div>
</section>
@endif
