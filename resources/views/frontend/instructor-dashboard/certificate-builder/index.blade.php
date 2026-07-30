{{-- 2026-06-12 — Per-coach certificate builder (coach panel), enterprise UI v2.
     Scoped to the coach's own template. Scale-aware vanilla-JS drag (no jQuery):
     the 930×600 canvas is scaled to fit so the whole certificate is visible,
     while stored positions stay in true 930×600 coords matching the PDF. The
     drag save shows a live "Saved" indicator. CSP nonce'd. --}}
@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    $cbBg  = !empty($certificate->background) ? asset($certificate->background) : null;
    $cbSig = !empty($certificate->signature)  ? asset($certificate->signature)  : null;

    // Realistic sample data + preview-init values (used by both the form's
    // "Preview sample" fields and the live preview, so define once up front).
    $cbSample = ['student' => 'Aarav Sharma', 'course' => 'Advanced Yoga Training Program',
                 'date' => '09 July 2026', 'certId' => 'CERT-2026-001'];
    $cbFontFaces = [
        'serif' => "Georgia,'Times New Roman',serif",
        'sans'  => "system-ui,-apple-system,Arial,sans-serif",
        'mono'  => "'Courier New',monospace",
    ];
    $cbPaperDefaults = ['classic' => '#faf7f0', 'modern' => '#ffffff', 'royal' => '#fbf6ea'];
    $cbInitFace  = $cbFontFaces[$certFont] ?? $cbFontFaces['serif'];
    $cbInitPaper = $paperColor ?: ($cbPaperDefaults[$certTemplate] ?? '#faf7f0');
@endphp

<div class="cb">
    {{-- ── Hero header ── --}}
    <div class="cb-hero">
        <div class="cb-hero__main">
            <span class="cb-hero__eyebrow"><i class="bi bi-award"></i> {{ __('Brand asset') }}</span>
            <h1 class="cb-hero__title">{{ __('Certificate Builder') }}</h1>
            <p class="cb-hero__sub">{{ __('Design the certificate your students receive when they complete your courses. Fully branded to you.') }}</p>
        </div>
        <button type="submit" form="cbForm" class="cb-btn cb-btn--primary cb-hero__cta">
            <i class="bi bi-check2-circle"></i> {{ __('Save certificate') }}
        </button>
    </div>

    {{-- ── Tag strip ── --}}
    <div class="cb-tagbar">
        <span class="cb-tagbar__label"><i class="bi bi-braces-asterisk"></i> {{ __('Dynamic tags') }}</span>
        <div class="cb-tagbar__chips">
            @foreach (['[student_name]','[platform_name]','[course]','[date]','[instructor_name]'] as $tag)
                <code class="cb-chip">{{ $tag }}</code>
            @endforeach
        </div>
        <span class="cb-tagbar__hint">{{ __('Type them anywhere — they fill in per student automatically.') }}</span>
    </div>

    <form action="{{ route('instructor.certificate-builder.update') }}" method="POST" enctype="multipart/form-data" id="cbForm">
        @csrf
        @method('PUT')

        <div class="cb-grid">
            {{-- ───────── Form column ───────── --}}
            <section class="cb-panel">
                {{-- 2026-07-08 — certificate style: enterprise (default, framed +
                     sealed + verifiable) or classic (legacy custom background). --}}
                @php $cbStyle = $certificate->certificate_style ?? 'enterprise'; @endphp
                <div class="cb-field">
                    <label>{{ __('Certificate style') }}</label>
                    <div class="cb-style">
                        <label class="cb-style__opt {{ $cbStyle !== 'classic' ? 'is-on' : '' }}">
                            <input type="radio" name="certificate_style" value="enterprise" {{ $cbStyle !== 'classic' ? 'checked' : '' }}>
                            <span class="cb-style__check"><i class="bi bi-check-lg"></i></span>
                            <span class="cb-style__t">{{ __('Enterprise') }} <b class="cb-style__rec">{{ __('Recommended') }}</b></span>
                            <span class="cb-style__d">{{ __('Framed, sealed & verifiable with a QR — uses your brand colour & logo.') }}</span>
                        </label>
                        <label class="cb-style__opt {{ $cbStyle === 'classic' ? 'is-on' : '' }}">
                            <input type="radio" name="certificate_style" value="classic" {{ $cbStyle === 'classic' ? 'checked' : '' }}>
                            <span class="cb-style__check"><i class="bi bi-check-lg"></i></span>
                            <span class="cb-style__t">{{ __('Classic') }}</span>
                            <span class="cb-style__d">{{ __('Your custom background with drag-positioned text (legacy).') }}</span>
                        </label>
                    </div>
                </div>

                {{-- 2026-07-09 — Enterprise DESIGN gallery + accent colour (only
                     relevant for the Enterprise style; hidden for Classic). --}}
                <div class="cb-entopts" id="cbEntOpts" @if($cbStyle === 'classic') hidden @endif>
                    <div class="cb-field">
                        <label>{{ __('Enterprise design') }} <span class="cb-dim">{{ __('pick a look') }}</span></label>
                        <div class="cb-tpl" style="--cb-thumb: {{ $accentColor ?: $brandColor }};">
                            @foreach ([
                                ['v' => 'classic', 't' => __('Classic'), 'd' => __('Framed & sealed')],
                                ['v' => 'modern',  't' => __('Modern'),  'd' => __('Minimal band')],
                                ['v' => 'royal',   't' => __('Royal'),   'd' => __('Ornate & elegant')],
                            ] as $tpl)
                                <label class="cb-tpl__opt {{ $certTemplate === $tpl['v'] ? 'is-on' : '' }}">
                                    <input type="radio" name="certificate_template" value="{{ $tpl['v'] }}" {{ $certTemplate === $tpl['v'] ? 'checked' : '' }}>
                                    <span class="cb-tpl__thumb cb-tpl__thumb--{{ $tpl['v'] }}"></span>
                                    <span class="cb-tpl__t">{{ $tpl['t'] }}</span>
                                    <span class="cb-tpl__d">{{ $tpl['d'] }}</span>
                                    <span class="cb-tpl__check"><i class="bi bi-check-lg"></i></span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="cb-field">
                        <label>{{ __('Accent colour') }} <span class="cb-dim">{{ __('overrides your brand colour on the certificate') }}</span></label>
                        <div class="cb-accent">
                            <span class="cb-accent__pickwrap">
                                <input type="color" id="cbAccentPick" value="{{ $accentColor ?: $brandColor }}" aria-label="{{ __('Accent colour') }}">
                            </span>
                            <input type="text" name="accent_color" id="cbAccentHex" class="cb-input cb-accent__hex" value="{{ $accentColor }}" placeholder="{{ $brandColor }}" maxlength="7" spellcheck="false">
                            <button type="button" class="cb-accent__reset" id="cbAccentReset" data-brand="{{ $brandColor }}">{{ __('Brand colour') }}</button>
                        </div>
                        <span class="cb-dim">{{ __('Leave blank to use your brand colour automatically.') }}</span>
                    </div>

                    {{-- 2026-07-09 — Typography & layout (DomPDF-safe: preview == export). --}}
                    <div class="cb-field">
                        <label>{{ __('Font style') }} <span class="cb-dim">{{ __('applied to the certificate') }}</span></label>
                        <div class="cb-seg" id="cbFontSeg" role="group" aria-label="{{ __('Font style') }}">
                            @foreach ([
                                ['v' => 'serif', 't' => __('Serif'),      'f' => "Georgia,'Times New Roman',serif"],
                                ['v' => 'sans',  't' => __('Sans-serif'), 'f' => "system-ui,-apple-system,Arial,sans-serif"],
                                ['v' => 'mono',  't' => __('Monospace'),  'f' => "'Courier New',monospace"],
                            ] as $fo)
                                <button type="button" class="cb-seg__b {{ $certFont === $fo['v'] ? 'is-on' : '' }}" data-font="{{ $fo['v'] }}" data-face="{{ $fo['f'] }}">{{ $fo['t'] }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="font_family" id="cbFontInput" value="{{ $certFont }}">
                    </div>

                    <div class="cb-field">
                        <label>{{ __('Text alignment') }}</label>
                        <div class="cb-seg" id="cbAlignSeg" role="group" aria-label="{{ __('Text alignment') }}">
                            <button type="button" class="cb-seg__b {{ $certAlign === 'center' ? 'is-on' : '' }}" data-align="center"><i class="bi bi-text-center"></i> {{ __('Center') }}</button>
                            <button type="button" class="cb-seg__b {{ $certAlign === 'left' ? 'is-on' : '' }}" data-align="left"><i class="bi bi-text-left"></i> {{ __('Left') }}</button>
                        </div>
                        <input type="hidden" name="text_align" id="cbAlignInput" value="{{ $certAlign }}">
                    </div>

                    <div class="cb-field">
                        <label>{{ __('Paper colour') }} <span class="cb-dim">{{ __('overrides the design default') }}</span></label>
                        <div class="cb-tones" id="cbTones">
                            @foreach (['#faf7f0', '#ffffff', '#fbf6ea', '#f5f7fb', '#f4f9f6'] as $tone)
                                <button type="button" class="cb-tone {{ $paperColor === $tone ? 'is-on' : '' }}" style="background: {{ $tone }};" data-c="{{ $tone }}" aria-label="{{ $tone }}"></button>
                            @endforeach
                            <span class="cb-tone__pick"><input type="color" id="cbPaperPick" value="{{ $paperColor ?: '#faf7f0' }}" aria-label="{{ __('Custom paper colour') }}"></span>
                            <button type="button" class="cb-accent__reset" id="cbPaperReset">{{ __('Design default') }}</button>
                        </div>
                        <input type="hidden" name="paper_color" id="cbPaperInput" value="{{ $paperColor }}">
                    </div>
                </div>

                <div class="cb-divider"></div>

                <div class="cb-panel__head">
                    <span class="cb-panel__ic"><i class="bi bi-fonts"></i></span>
                    <div>
                        <h2 class="cb-panel__title">{{ __('Content') }}</h2>
                        <p class="cb-panel__desc">{{ __('Wording shown on the certificate') }} <small style="color:#94a3b8;">— {{ __('used in Classic style') }}</small></p>
                    </div>
                </div>

                <div class="cb-field">
                    <label>{{ __('Title') }}</label>
                    <input type="text" name="title" class="cb-input" value="{{ $certificate->title }}" placeholder="{{ __('Certificate of Achievement') }}">
                </div>

                <div class="cb-field">
                    <label>{{ __('Sub title') }}</label>
                    <input type="text" name="sub_title" class="cb-input" value="{{ $certificate->sub_title }}" placeholder="{{ __('Web Designing') }}">
                </div>

                <div class="cb-field">
                    <label>{{ __('Description') }}</label>
                    <textarea name="description" class="cb-input" rows="4" placeholder="{{ __('This certificate is awarded to [student_name] for completing [course]…') }}">{{ $certificate->description }}</textarea>
                    @error('description') <span class="cb-err">{{ $message }}</span> @enderror
                </div>

                <div class="cb-divider"></div>

                <div class="cb-panel__head cb-panel__head--sub">
                    <span class="cb-panel__ic"><i class="bi bi-image"></i></span>
                    <div>
                        <h2 class="cb-panel__title">{{ __('Artwork') }}</h2>
                        <p class="cb-panel__desc">{{ __('Background & signature images') }}</p>
                    </div>
                </div>

                <div class="cb-field">
                    <label>{{ __('Background') }} <span class="cb-dim">{{ __('930 × 600 px') }}</span></label>
                    <label class="cb-drop">
                        @if($cbBg)
                            <img src="{{ $cbBg }}" class="cb-drop__thumb cb-drop__thumb--wide" alt="">
                        @else
                            <span class="cb-drop__ic"><i class="bi bi-cloud-arrow-up"></i></span>
                        @endif
                        <span class="cb-drop__txt">{{ $cbBg ? __('Replace background') : __('Upload background') }}<small>{{ __('JPG / PNG / WEBP') }}</small></span>
                        <input type="file" name="background" accept="image/png,image/jpeg,image/webp">
                    </label>
                    @error('background') <span class="cb-err">{{ $message }}</span> @enderror
                </div>

                <div class="cb-field">
                    <label>{{ __('Signature') }} <span class="cb-dim">{{ __('transparent PNG') }}</span></label>
                    <label class="cb-drop">
                        @if($cbSig)
                            <img src="{{ $cbSig }}" class="cb-drop__thumb" alt="">
                        @else
                            <span class="cb-drop__ic"><i class="bi bi-vector-pen"></i></span>
                        @endif
                        <span class="cb-drop__txt">{{ $cbSig ? __('Replace signature') : __('Upload signature') }}<small>{{ __('PNG recommended') }}</small></span>
                        <input type="file" name="signature" accept="image/png,image/jpeg,image/webp">
                    </label>
                    @error('signature') <span class="cb-err">{{ $message }}</span> @enderror
                </div>

                <div class="cb-divider"></div>

                {{-- 2026-07-09 — preview-only sample data. NOT saved: real students
                     fill these automatically on export. Lets the coach sanity-check
                     long names / their own branding in the live preview. --}}
                <div class="cb-panel__head cb-panel__head--sub">
                    <span class="cb-panel__ic"><i class="bi bi-eye"></i></span>
                    <div>
                        <h2 class="cb-panel__title">{{ __('Preview sample') }}</h2>
                        <p class="cb-panel__desc">{{ __('Try realistic values — not saved; real students fill these on export.') }}</p>
                    </div>
                </div>

                <div class="cb-grid2">
                    <div class="cb-field">
                        <label>{{ __('Student name') }}</label>
                        <input type="text" class="cb-input" id="cbSmpStudent" value="{{ $cbSample['student'] }}" data-sample>
                    </div>
                    <div class="cb-field">
                        <label>{{ __('Course name') }}</label>
                        <input type="text" class="cb-input" id="cbSmpCourse" value="{{ $cbSample['course'] }}" data-sample>
                    </div>
                    <div class="cb-field">
                        <label>{{ __('Instructor') }}</label>
                        <input type="text" class="cb-input" id="cbSmpInstr" value="{{ $coachName }}" data-sample>
                    </div>
                    <div class="cb-field">
                        <label>{{ __('Platform / institute') }}</label>
                        <input type="text" class="cb-input" id="cbSmpBrand" value="{{ $brandName }}" data-sample>
                    </div>
                    <div class="cb-field">
                        <label>{{ __('Completion date') }}</label>
                        <input type="text" class="cb-input" id="cbSmpDate" value="{{ $cbSample['date'] }}" data-sample>
                    </div>
                    <div class="cb-field">
                        <label>{{ __('Certificate ID') }} <span class="cb-dim">{{ __('auto') }}</span></label>
                        <input type="text" class="cb-input" id="cbSmpCert" value="{{ $cbSample['certId'] }}" data-sample>
                    </div>
                </div>

                <button type="submit" class="cb-btn cb-btn--primary cb-btn--block">
                    <i class="bi bi-check2-circle"></i> {{ __('Save certificate') }}
                </button>
            </section>

            {{-- ───────── Preview column (sticky) — live, switches with the template ───────── --}}
            <section class="cb-preview">
                <div class="cb-preview__toolbar">
                    <div class="cb-preview__title"><span class="cb-dot"></span> {{ __('Live preview') }}</div>
                    <div class="cb-preview__meta">
                        <span class="cb-pill cb-pill--style" id="cbStyleBadge">{{ $cbStyle !== 'classic' ? __('Enterprise') : __('Classic') }}</span>
                        <span class="cb-pill" id="cbSaved" @if($cbStyle !== 'classic') hidden @endif><i class="bi bi-arrows-move"></i> {{ __('Drag to position') }}</span>
                        <button type="button" class="cb-reset" id="cbResetLayout" @if($cbStyle !== 'classic') hidden @endif title="{{ __('Restore the default, non-overlapping layout') }}"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Reset layout') }}</button>
                        <span class="cb-dim" id="cbDim">{{ $cbStyle !== 'classic' ? 'A4 landscape' : '930 × 600' }}</span>
                    </div>
                </div>

                {{-- 2026-07-09 — device preview toggle (checks the layout at each width). --}}
                <div class="cb-devices" id="cbDevices" role="group" aria-label="{{ __('Preview width') }}">
                    <button type="button" class="cb-dev is-on" data-dev="desktop"><i class="bi bi-display"></i> {{ __('Desktop') }}</button>
                    <button type="button" class="cb-dev" data-dev="tablet"><i class="bi bi-tablet"></i> {{ __('Tablet') }}</button>
                    <button type="button" class="cb-dev" data-dev="mobile"><i class="bi bi-phone"></i> {{ __('Mobile') }}</button>
                </div>

                <div class="cb-frame">
                    {{-- ENTERPRISE preview (HTML replica of the exported PDF) --}}
                    <div class="cb-stage cb-stage--ent" id="cbStageEnt" @if($cbStyle === 'classic') hidden @endif>
                        <div class="cb-ent" id="cbEnt" data-tpl="{{ $certTemplate }}" data-al="{{ $certAlign }}"
                             style="--cb-brand: {{ $accentColor ?: $brandColor }}; --cb-font: {{ $cbInitFace }}; --cb-paper: {{ $cbInitPaper }};">
                            <div class="cb-ent__band"></div>
                            <div class="cb-ent__frame"></div><div class="cb-ent__frame2"></div>
                            <span class="cb-ent__corner c-tl"></span><span class="cb-ent__corner c-tr"></span>
                            <span class="cb-ent__corner c-bl"></span><span class="cb-ent__corner c-br"></span>
                            <div class="cb-ent__in">
                                @if($brandLogo)<img class="cb-ent__logo" src="{{ $brandLogo }}" alt="">@endif
                                <div class="cb-ent__brand" id="cbEntBrand">{{ $brandName }}</div>
                                <div class="cb-ent__eyebrow">CERTIFICATE OF COMPLETION</div>
                                <div class="cb-ent__eyebrow2">THIS IS PROUDLY PRESENTED TO</div>
                                <div class="cb-ent__rule"></div>
                                <div class="cb-ent__name" id="cbEntName">{{ $cbSample['student'] }}</div>
                                <div class="cb-ent__nameline"></div>
                                <div class="cb-ent__body">has successfully completed the course</div>
                                <div class="cb-ent__course" id="cbEntCourse">{{ $cbSample['course'] }}</div>
                                <div class="cb-ent__meta">Completed on <span id="cbEntDate">{{ $cbSample['date'] }}</span></div>
                            </div>
                            <div class="cb-ent__seal">
                                <span class="cb-ent__seal-o"></span><span class="cb-ent__seal-i"></span>
                                <span class="cb-ent__seal-m" id="cbEntSealM">{{ strtoupper(mb_substr($brandName, 0, 1)) }}</span>
                                <span class="cb-ent__seal-v">VERIFIED</span>
                            </div>
                            <table class="cb-ent__foot">
                                <tr>
                                    <td>
                                        <img class="cb-ent__sig" id="cbEntSig" src="{{ $cbSig ?: '' }}" alt="" @unless($cbSig) style="display:none" @endunless>
                                        <span class="cb-ent__sig-mark" id="cbEntSigName" @if($cbSig) style="display:none" @endif>{{ $coachName }}</span>
                                        <span class="cb-ent__sig-line"></span>
                                        <span class="cb-ent__sig-name" id="cbEntInstr">{{ $coachName }}</span>
                                        <span class="cb-ent__sig-role">Instructor</span>
                                    </td>
                                    <td>
                                        <span class="cb-ent__qr" aria-hidden="true"><i class="bi bi-qr-code"></i></span>
                                        <span class="cb-ent__cred">ID · <span id="cbEntCred">{{ $cbSample['certId'] }}</span></span>
                                        <span class="cb-ent__verify">{{ preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')) }}/verify</span>
                                    </td>
                                    <td>
                                        <span class="cb-ent__sig-mark" id="cbEntBrand2">{{ $brandName }}</span>
                                        <span class="cb-ent__sig-line"></span>
                                        <span class="cb-ent__sig-name" id="cbEntBrand3">{{ $brandName }}</span>
                                        <span class="cb-ent__sig-role">Issuing Academy</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    {{-- CLASSIC preview (draggable, custom background) --}}
                    <div class="cb-stage cb-stage--classic" id="cbStageClassic" @if($cbStyle !== 'classic') hidden @endif>
                        <div class="cb-canvas" id="cbCanvas" @if($cbBg) style="background-image:url('{{ $cbBg }}');" @endif>
                            <div id="title" class="cb-el cb-el--title"></div>
                            <div id="sub_title" class="cb-el cb-el--sub"></div>
                            <div id="description" class="cb-el cb-el--desc"></div>
                            <div id="signature" class="cb-el cb-el--sig"><img id="cbSigImg" src="{{ $cbSig ?: '' }}" alt="signature" @unless($cbSig) style="display:none" @endunless></div>
                        </div>
                    </div>
                </div>
                <p class="cb-dim cb-foot" id="cbFoot">{{ $cbStyle !== 'classic'
                    ? __('Enterprise uses your brand colour, logo & signature. The exported PDF is A4 landscape and matches this layout.')
                    : __('Scaled to fit — the exported certificate is 930 × 600 px and matches this layout.') }}</p>
            </section>
        </div>
    </form>
</div>

<style>
    .cb { --ink:#0f172a; --muted:#64748b; --line:#e9edf3; --brand:#4f46e5; --brand-soft:#ecfdf5; max-width:1220px; color:var(--ink); }

    /* Hero */
    .cb-hero { position:relative; display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap;
        background:linear-gradient(120deg,#4f46e5 0%, #7c3aed 100%); border-radius:20px; padding:26px 28px; margin-bottom:18px; overflow:hidden; box-shadow:0 12px 30px rgba(79,70,229,.22); }
    .cb-hero::after { content:''; position:absolute; right:-40px; top:-60px; width:240px; height:240px; background:radial-gradient(circle,rgba(255,255,255,.18),transparent 70%); }
    .cb-hero__eyebrow { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.18); color:#fff; font-size:12px; font-weight:600; padding:5px 11px; border-radius:999px; }
    .cb-hero__title { color:#fff; font-size:26px; font-weight:800; margin:10px 0 5px; letter-spacing:-.4px; }
    .cb-hero__sub { color:rgba(255,255,255,.85); font-size:14px; margin:0; max-width:560px; }
    .cb-hero__cta { position:relative; z-index:1; }

    /* Tag bar */
    .cb-tagbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; background:#fff; border:1px solid var(--line); border-radius:14px; padding:12px 16px; margin-bottom:20px; }
    .cb-tagbar__label { display:inline-flex; align-items:center; gap:6px; font-weight:700; font-size:12.5px; color:#334155; }
    .cb-tagbar__chips { display:flex; gap:7px; flex-wrap:wrap; }
    .cb-chip { background:var(--brand-soft); color:#065f46; border-radius:7px; padding:4px 9px; font-size:12px; font-weight:600; }
    .cb-tagbar__hint { color:#94a3b8; font-size:12.5px; margin-left:auto; }

    /* Grid */
    .cb-grid { display:grid; grid-template-columns: 400px 1fr; gap:22px; align-items:start; }
    @media (max-width:1024px){ .cb-grid { grid-template-columns:1fr; } }

    /* Panel */
    .cb-panel, .cb-preview { background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:0 1px 3px rgba(15,23,42,.04); }
    .cb-panel { padding:22px; }
    .cb-panel__head { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .cb-panel__head--sub { margin-top:4px; }
    .cb-panel__ic { flex:0 0 auto; width:38px; height:38px; border-radius:10px; background:var(--brand-soft); color:var(--brand); display:inline-flex; align-items:center; justify-content:center; font-size:17px; }
    .cb-panel__title { font-size:15px; font-weight:700; margin:0; }
    .cb-panel__desc { font-size:12.5px; color:var(--muted); margin:1px 0 0; }
    .cb-divider { height:1px; background:var(--line); margin:20px 0; }

    .cb-field { margin-bottom:15px; }
    .cb-style { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:6px; }
    @media (max-width:560px){ .cb-style { grid-template-columns:1fr; } }
    .cb-style__opt { position:relative; border:1.5px solid #e2e8f0; border-radius:12px; padding:12px 13px; cursor:pointer; display:block; transition:border-color .15s, box-shadow .15s, background .15s; }
    .cb-style__opt:hover { border-color:#c7d2fe; }
    .cb-style__opt.is-on, .cb-style__opt:has(input:checked) { border-color:var(--brand); background:#f5f3ff; box-shadow:0 0 0 3px color-mix(in srgb, var(--brand) 16%, transparent); }
    .cb-style__opt input { position:absolute; opacity:0; }
    .cb-style__t { display:block; font-weight:700; font-size:13.5px; color:var(--ink); }
    .cb-style__rec { font-size:10px; font-weight:700; color:#fff; background:var(--brand); padding:2px 6px; border-radius:5px; margin-left:5px; vertical-align:middle; }
    .cb-style__d { display:block; font-size:11.5px; color:var(--muted); margin-top:4px; line-height:1.45; }
    .cb-field > label { display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
    .cb-dim { color:#94a3b8; font-weight:500; font-size:11.5px; }
    .cb-input { width:100%; border:1px solid #e2e8f0; border-radius:11px; padding:11px 13px; font-size:14px; color:var(--ink); background:#fff; transition:border-color .15s, box-shadow .15s; }
    .cb-input::placeholder { color:#b6c0cd; }
    .cb-input:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(79,70,229,.14); }
    textarea.cb-input { resize:vertical; min-height:92px; line-height:1.5; }
    .cb-err { display:block; color:#dc2626; font-size:12.5px; margin-top:5px; }

    /* Dropzone */
    .cb-drop { display:flex; align-items:center; gap:13px; border:1.5px dashed #d4dbe6; border-radius:13px; padding:12px 14px; cursor:pointer; transition:border-color .15s, background .15s; position:relative; }
    .cb-drop:hover { border-color:var(--brand); background:#fafbff; }
    .cb-drop input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .cb-drop__ic { width:42px; height:42px; flex:0 0 auto; border-radius:10px; background:#f1f5f9; color:#64748b; display:inline-flex; align-items:center; justify-content:center; font-size:19px; }
    .cb-drop__thumb { height:42px; width:42px; flex:0 0 auto; object-fit:contain; border:1px solid #e2e8f0; border-radius:9px; background:#fff; padding:3px; }
    .cb-drop__thumb--wide { width:74px; }
    .cb-drop__txt { font-size:13px; font-weight:600; color:#334155; display:flex; flex-direction:column; }
    .cb-drop__txt small { font-weight:500; color:#94a3b8; font-size:11.5px; }

    /* Buttons */
    .cb-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:none; border-radius:11px; padding:11px 18px; font-size:14px; font-weight:600; cursor:pointer; transition:filter .15s, box-shadow .15s, transform .05s; }
    .cb-btn--primary { background:#fff; color:var(--brand); box-shadow:0 2px 8px rgba(15,23,42,.12); }
    .cb-hero .cb-btn--primary { background:#fff; color:var(--brand); }
    .cb-btn--block { width:100%; margin-top:6px; background:var(--brand); color:#fff; box-shadow:0 4px 12px rgba(79,70,229,.28); }
    .cb-btn--block:hover { filter:brightness(1.06); box-shadow:0 8px 20px rgba(79,70,229,.34); }
    .cb-btn:active { transform:translateY(1px); }
    .cb-hero__cta:hover { filter:brightness(1.04); box-shadow:0 8px 20px rgba(0,0,0,.18); }

    /* Preview */
    .cb-preview { padding:18px; position:sticky; top:84px; }
    .cb-preview__toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .cb-preview__title { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:700; color:var(--ink); }
    .cb-dot { width:9px; height:9px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.18); }
    .cb-preview__meta { display:flex; align-items:center; gap:12px; }
    .cb-pill { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#475569; background:#f1f5f9; border-radius:999px; padding:5px 11px; transition:background .2s, color .2s; }
    .cb-pill.is-saved { background:#dcfce7; color:#15803d; }

    .cb-frame { background:linear-gradient(180deg,#f8fafc,#eef2f7); border:1px solid var(--line); border-radius:14px; padding:18px; }
    .cb-stage { position:relative; width:100%; border-radius:8px; overflow:hidden; box-shadow:0 10px 30px rgba(15,23,42,.16); }
    .cb-canvas { width:930px; height:600px; position:absolute; top:0; left:0; transform-origin:top left; background:#faf7ef; background-size:cover; background-position:center; background-repeat:no-repeat; }
    .cb-el { position:absolute; cursor:grab; user-select:none; touch-action:none; padding:2px 4px; border-radius:4px; transition:box-shadow .12s, background .12s; }
    .cb-el:hover { box-shadow:0 0 0 1.5px var(--brand), 0 0 0 5px rgba(79,70,229,.14); background:rgba(79,70,229,.05); }
    .cb-el:active { cursor:grabbing; }
    /* Fonts mirror the exported PDF exactly (true WYSIWYG). */
    .cb-el--title { font-size:22px; font-weight:bold; color:#000; }
    .cb-el--sub   { font-size:18px; color:#000; }
    .cb-el--desc  { font-size:16px; color:#000; line-height:1.4; }
    .cb-el--sig img { max-height:70px; display:block; }
    .cb-foot { margin:12px 2px 0; }

    /* Default positions — a safety net so an element the coach hasn't positioned
       never renders at top:0 and overlaps the header. The saved-position loop
       below overrides these with the real coordinates. */
    .cb-canvas #title { top: 210px; }
    .cb-canvas #sub_title { top: 245px; }
    .cb-canvas #description { top: 281px; }
    .cb-canvas #signature { top: 392px; left: 398px; }

    /* Saved positions (vertical for centred rows; both axes for signature). */
    @foreach ($certificateItems as $item)
        .cb-canvas #{{ $item->element_id }} { left: {{ (int) $item->x_position }}px; top: {{ (int) $item->y_position }}px; }
    @endforeach

    /* Render-accurate: text rows are centred horizontally (matches the PDF),
       so only their vertical position is meaningful. MUST come after the loop. */
    .cb-canvas #title, .cb-canvas #sub_title, .cb-canvas #description {
        left:50% !important; transform:translateX(-50%); width:730px; text-align:center; cursor:ns-resize;
    }

    /* Template-card checkmark + selected state */
    .cb-style__check { position:absolute; top:10px; right:10px; width:21px; height:21px; border-radius:50%;
        background:#fff; border:1.5px solid #cbd5e1; color:#fff; display:inline-flex; align-items:center; justify-content:center;
        font-size:12px; opacity:0; transform:scale(.6); transition:all .18s cubic-bezier(.34,1.56,.64,1); }
    .cb-style__opt.is-on .cb-style__check, .cb-style__opt:has(input:checked) .cb-style__check {
        opacity:1; transform:scale(1); background:var(--brand); border-color:var(--brand); }

    /* Preview stages fade on template switch */
    .cb-stage { transition:opacity .25s ease; }
    .cb-stage[hidden] { display:none; }
    .cb-pill--style { background:#eef2ff; color:var(--brand); font-weight:700; }

    /* ── Enterprise live-preview replica (mirrors the exported PDF) ── */
    .cb-stage--ent { position:relative; width:100%; border-radius:8px; overflow:hidden; box-shadow:0 12px 34px rgba(15,23,42,.18); }
    .cb-ent { width:1000px; height:707px; position:absolute; top:0; left:0; transform-origin:top left;
        background:var(--cb-paper, #faf7f0); font-family:var(--cb-font, Georgia,'Times New Roman',serif); color:#1a1a1a; }
    .cb-ent__frame { position:absolute; top:22px; left:22px; right:22px; bottom:22px; border:2px solid var(--cb-brand); }
    .cb-ent__frame2 { position:absolute; top:29px; left:29px; right:29px; bottom:29px; border:1px solid #b08d4f; }
    .cb-ent__corner { position:absolute; width:34px; height:34px; }
    .cb-ent__corner.c-tl { top:14px; left:14px; border-top:2px solid #b08d4f; border-left:2px solid #b08d4f; }
    .cb-ent__corner.c-tr { top:14px; right:14px; border-top:2px solid #b08d4f; border-right:2px solid #b08d4f; }
    .cb-ent__corner.c-bl { bottom:14px; left:14px; border-bottom:2px solid #b08d4f; border-left:2px solid #b08d4f; }
    .cb-ent__corner.c-br { bottom:14px; right:14px; border-bottom:2px solid #b08d4f; border-right:2px solid #b08d4f; }
    .cb-ent__in { position:absolute; top:52px; left:80px; right:80px; text-align:center; }
    /* Text alignment (left keeps the seal & footer where they are). */
    .cb-ent[data-al="left"] .cb-ent__in { text-align:left; }
    .cb-ent[data-al="left"] .cb-ent__rule,
    .cb-ent[data-al="left"] .cb-ent__nameline { margin-left:0; margin-right:0; }
    .cb-ent__logo { height:40px; margin-bottom:6px; object-fit:contain; }
    .cb-ent__brand { font-family:sans-serif; font-size:16px; font-weight:800; letter-spacing:.3px; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cb-ent__eyebrow { font-family:sans-serif; font-size:15px; font-weight:800; letter-spacing:7px; color:var(--cb-brand); margin-top:22px; }
    .cb-ent__eyebrow2 { font-family:sans-serif; font-size:10px; letter-spacing:4px; color:#b08d4f; margin-top:6px; }
    .cb-ent__rule { width:64px; height:2px; background:#b08d4f; margin:14px auto 0; }
    .cb-ent__name { font-size:52px; color:var(--cb-brand); margin-top:14px; line-height:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cb-ent__nameline { width:340px; height:1px; background:#b08d4f; margin:12px auto 0; }
    .cb-ent__body { font-size:15px; color:#2a2a2a; margin-top:16px; }
    .cb-ent__course { font-size:26px; font-weight:800; margin-top:8px; }
    .cb-ent__meta { font-family:sans-serif; font-size:12px; color:#6b6b6b; margin-top:8px; letter-spacing:.3px; }
    .cb-ent__seal { position:absolute; top:322px; right:118px; width:96px; height:96px; text-align:center; }
    .cb-ent__seal-o { position:absolute; inset:0; border:2px solid #b08d4f; border-radius:50%; }
    .cb-ent__seal-i { position:absolute; top:15px; left:15px; width:66px; height:66px; background:var(--cb-brand); border:2px solid #b08d4f; border-radius:50%; }
    .cb-ent__seal-m { position:absolute; top:26px; left:0; width:96px; color:#fff; font-size:24px; font-weight:800; }
    .cb-ent__seal-v { position:absolute; top:56px; left:0; width:96px; color:#e6ce97; font-family:sans-serif; font-size:8px; letter-spacing:2px; }
    .cb-ent__foot { position:absolute; bottom:58px; left:80px; right:80px; width:auto; border-collapse:collapse; }
    .cb-ent__foot td { width:33%; text-align:center; vertical-align:bottom; }
    .cb-ent__sig { height:32px; }
    .cb-ent__sig-mark { display:block; font-size:19px; height:30px; }
    .cb-ent__sig-line { display:block; border-top:1px solid #555; width:170px; margin:5px auto 0; }
    .cb-ent__sig-name { display:block; font-family:sans-serif; font-size:12px; font-weight:700; margin-top:6px; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding:0 6px; }
    .cb-ent__sig-mark { max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding:0 6px; }
    .cb-ent__sig-role { display:block; font-family:sans-serif; font-size:8px; letter-spacing:1px; color:#6b6b6b; text-transform:uppercase; margin-top:2px; }
    .cb-ent__qr { display:inline-flex; align-items:center; justify-content:center; width:52px; height:52px; border:1px solid #d9d2bf; border-radius:5px; background:#fff; color:#1a1a1a; font-size:38px; line-height:1; }
    .cb-ent__cred { display:block; font-family:sans-serif; font-size:8px; color:#555; margin-top:5px; }
    .cb-ent__verify { display:block; font-family:sans-serif; font-size:7px; letter-spacing:.3px; color:var(--cb-brand); text-transform:uppercase; margin-top:2px; }

    /* ── Enterprise design gallery (Classic / Modern / Royal) ── */
    .cb-entopts[hidden] { display:none; }
    .cb-tpl { display:grid; grid-template-columns:repeat(3,1fr); gap:9px; margin-top:6px; }
    .cb-tpl__opt { position:relative; border:1.5px solid #e2e8f0; border-radius:12px; padding:9px 9px 10px; cursor:pointer; text-align:center; transition:border-color .15s, box-shadow .15s, background .15s; }
    .cb-tpl__opt:hover { border-color:#c7d2fe; }
    .cb-tpl__opt.is-on, .cb-tpl__opt:has(input:checked) { border-color:var(--brand); background:#f5f3ff; box-shadow:0 0 0 3px color-mix(in srgb, var(--brand) 15%, transparent); }
    .cb-tpl__opt input { position:absolute; opacity:0; pointer-events:none; }
    .cb-tpl__thumb { display:block; height:48px; border-radius:7px; margin-bottom:8px; position:relative; overflow:hidden; background:#faf7f0; }
    /* miniature of each design so the choice is visual, tinted with the accent */
    .cb-tpl__thumb--classic { background:#faf7f0; border:2px solid var(--cb-thumb, #0f766e); }
    .cb-tpl__thumb--classic::after { content:''; position:absolute; inset:3px; border:1px solid #b08d4f; }
    .cb-tpl__thumb--modern { background:#fff; border:1px solid #eef2f7; }
    .cb-tpl__thumb--modern::before { content:''; position:absolute; top:0; left:0; right:0; height:9px; background:var(--cb-thumb, #0f766e); }
    .cb-tpl__thumb--modern::after { content:''; position:absolute; left:18%; right:18%; top:26px; height:2px; border-radius:2px; background:var(--cb-thumb, #0f766e); }
    .cb-tpl__thumb--royal { background:#fbf6ea; border:3px solid var(--cb-thumb, #b08d4f); }
    .cb-tpl__thumb--royal::after { content:''; position:absolute; inset:3px; border:1px solid #b08d4f; }
    .cb-tpl__t { display:block; font-size:12.5px; font-weight:700; color:var(--ink); }
    .cb-tpl__d { display:block; font-size:10.5px; color:var(--muted); margin-top:1px; }
    .cb-tpl__check { position:absolute; top:7px; right:7px; width:18px; height:18px; border-radius:50%; background:var(--brand); color:#fff; display:none; align-items:center; justify-content:center; font-size:10px; }
    .cb-tpl__opt.is-on .cb-tpl__check, .cb-tpl__opt:has(input:checked) .cb-tpl__check { display:inline-flex; }

    /* Accent-colour picker */
    .cb-accent { display:flex; align-items:center; gap:9px; margin-top:6px; }
    .cb-accent__pickwrap { flex:0 0 auto; width:42px; height:42px; border-radius:11px; border:1px solid #e2e8f0; overflow:hidden; padding:3px; background:#fff; }
    .cb-accent__pickwrap input[type=color] { width:100%; height:100%; border:none; padding:0; background:none; cursor:pointer; }
    .cb-accent__hex { max-width:140px; text-transform:lowercase; font-variant-numeric:tabular-nums; }
    .cb-accent__reset { border:1px solid #e2e8f0; background:#f8fafc; color:#475569; border-radius:10px; padding:9px 12px; font-size:12.5px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background .15s, border-color .15s; }
    .cb-accent__reset:hover { background:#eef2ff; border-color:#c7d2fe; color:var(--brand); }

    /* ── Enterprise preview: design variants (mirror enterprise.blade.php) ── */
    .cb-ent__band { display:none; }
    /* MODERN — clean; brand accent band on top, no ornate frame, white paper. */
    .cb-ent[data-tpl="modern"] .cb-ent__frame,
    .cb-ent[data-tpl="modern"] .cb-ent__frame2,
    .cb-ent[data-tpl="modern"] .cb-ent__corner { display:none; }
    .cb-ent[data-tpl="modern"] .cb-ent__band { display:block; position:absolute; top:0; left:0; right:0; height:17px; background:var(--cb-brand); }
    .cb-ent[data-tpl="modern"] .cb-ent__in { top:80px; }
    .cb-ent[data-tpl="modern"] .cb-ent__eyebrow { letter-spacing:8px; }
    .cb-ent[data-tpl="modern"] .cb-ent__eyebrow2 { color:#9aa1a8; }
    .cb-ent[data-tpl="modern"] .cb-ent__rule { background:var(--cb-brand); width:52px; }
    .cb-ent[data-tpl="modern"] .cb-ent__nameline { background:#e3e8ea; }
    .cb-ent[data-tpl="modern"] .cb-ent__seal-o { border-color:var(--cb-brand); }
    .cb-ent[data-tpl="modern"] .cb-ent__seal-i { background:#fff; border-color:var(--cb-brand); }
    .cb-ent[data-tpl="modern"] .cb-ent__seal-m { color:var(--cb-brand); }
    .cb-ent[data-tpl="modern"] .cb-ent__seal-v { color:var(--cb-brand); }
    .cb-ent[data-tpl="modern"] .cb-ent__sig-line { border-top-color:#cbd2d6; }
    /* ROYAL — ornate: heavier frame, larger corners, italic name. (Paper colour
       is driven by --cb-paper so the coach's override wins.) */
    .cb-ent[data-tpl="royal"] .cb-ent__frame { border-width:3px; }
    .cb-ent[data-tpl="royal"] .cb-ent__frame2 { top:27px; left:27px; right:27px; bottom:27px; border-width:1.5px; }
    .cb-ent[data-tpl="royal"] .cb-ent__corner { width:48px; height:48px; }
    .cb-ent[data-tpl="royal"] .cb-ent__eyebrow { letter-spacing:8px; }
    .cb-ent[data-tpl="royal"] .cb-ent__name { font-style:italic; }
    .cb-ent[data-tpl="royal"] .cb-ent__seal-o { border-width:3px; }

    /* ── Segmented controls (font style / text alignment) ── */
    .cb-seg { display:inline-flex; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:11px; padding:3px; gap:3px; margin-top:6px; flex-wrap:wrap; }
    .cb-seg__b { border:none; background:none; font-family:inherit; font-size:12.5px; font-weight:600; color:#475569;
        padding:7px 13px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .15s, color .15s; }
    .cb-seg__b:hover { color:var(--brand); }
    .cb-seg__b.is-on { background:#fff; color:var(--brand); box-shadow:0 1px 2px rgba(15,23,42,.08); }

    /* ── Paper-colour tones ── */
    .cb-tones { display:flex; align-items:center; gap:7px; margin-top:6px; flex-wrap:wrap; }
    .cb-tone { width:28px; height:28px; border-radius:8px; border:1px solid rgba(15,23,42,.12); cursor:pointer; padding:0; transition:box-shadow .15s; }
    .cb-tone:hover { box-shadow:0 0 0 2px #fff, 0 0 0 4px #c7d2fe; }
    .cb-tone.is-on { box-shadow:0 0 0 2px #fff, 0 0 0 4px var(--brand); }
    .cb-tone__pick { width:28px; height:28px; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden; padding:2px; background:#fff; flex:0 0 auto; }
    .cb-tone__pick input[type=color] { width:100%; height:100%; border:none; padding:0; background:none; cursor:pointer; }

    /* ── Device preview toggle ── */
    .cb-devices { display:flex; justify-content:center; gap:4px; background:#f1f5f9; border:1px solid var(--line); border-radius:11px; padding:4px; margin-bottom:14px; }
    .cb-dev { border:none; background:none; font-family:inherit; font-size:12.5px; font-weight:600; color:#64748b;
        padding:7px 14px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .15s, color .15s; }
    .cb-dev:hover { color:var(--brand); }
    .cb-dev.is-on { background:#fff; color:var(--brand); box-shadow:0 1px 2px rgba(15,23,42,.08); }

    /* Device widths applied to the preview frame */
    .cb-frame { transition:max-width .3s ease; margin:0 auto; }
    .cb-frame.dev-desktop { max-width:100%; }
    .cb-frame.dev-tablet { max-width:600px; }
    .cb-frame.dev-mobile { max-width:340px; }

    /* ── Sample-data grid ── */
    .cb-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:0 12px; }
    @media (max-width:560px){ .cb-grid2 { grid-template-columns:1fr; } }

    /* ── Reset-layout button (Classic toolbar) ── */
    .cb-reset { display:inline-flex; align-items:center; gap:6px; font-family:inherit; font-size:12px; font-weight:600;
        color:#475569; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:999px; padding:5px 11px; cursor:pointer;
        transition:background .15s, color .15s, border-color .15s; }
    .cb-reset:hover { background:#eef2ff; color:var(--brand); border-color:#c7d2fe; }
    .cb-reset:disabled { opacity:.6; cursor:default; }
    .cb-reset[hidden] { display:none; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components.
   Themes the BUILDER CHROME only (panels, toolbars, form fields, segmented controls).
   The live certificate preview (.cb-ent*, .cb-canvas, .cb-el*, .cb-tpl__thumb--*) is the
   end-customer artifact and is deliberately left in its designed light paper colours. */
html[data-theme="dark"] .cb{ --ink:#e2e8f0; --muted:#94a3b8; --line:#2a3a55; }
html[data-theme="dark"] .cb-tagbar{ background:#1e293b; }
html[data-theme="dark"] .cb-tagbar__label{ color:#e2e8f0; }
html[data-theme="dark"] .cb-panel,
html[data-theme="dark"] .cb-preview{ background:#1e293b; box-shadow:none; }
html[data-theme="dark"] .cb-field > label{ color:#e2e8f0; }
html[data-theme="dark"] .cb-input{ background:#1e293b; border-color:#2a3a55; }
html[data-theme="dark"] .cb-input::placeholder{ color:#64748b; }
html[data-theme="dark"] .cb-style__opt{ border-color:#2a3a55; }
html[data-theme="dark"] .cb-drop:hover{ background:#17233a; }
html[data-theme="dark"] .cb-drop__ic{ background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .cb-drop__thumb{ background:#1e293b; border-color:#2a3a55; }
html[data-theme="dark"] .cb-drop__txt{ color:#e2e8f0; }
html[data-theme="dark"] .cb-pill:not(.cb-pill--style):not(.is-saved){ background:#22304a; color:#94a3b8; }
html[data-theme="dark"] .cb-frame{ background:#17233a; border-color:#2a3a55; }
html[data-theme="dark"] .cb-tpl__opt{ border-color:#2a3a55; }
html[data-theme="dark"] .cb-accent__pickwrap{ background:#1e293b; border-color:#2a3a55; }
html[data-theme="dark"] .cb-accent__reset{ background:#17233a; border-color:#2a3a55; color:#94a3b8; }
html[data-theme="dark"] .cb-seg{ background:#22304a; border-color:#2a3a55; }
html[data-theme="dark"] .cb-seg__b{ color:#94a3b8; }
html[data-theme="dark"] .cb-seg__b.is-on{ background:#1e293b; }
html[data-theme="dark"] .cb-tone__pick{ background:#1e293b; border-color:#2a3a55; }
html[data-theme="dark"] .cb-devices{ background:#22304a; }
html[data-theme="dark"] .cb-dev{ color:#94a3b8; }
html[data-theme="dark"] .cb-dev.is-on{ background:#1e293b; }
html[data-theme="dark"] .cb-reset{ background:#22304a; border-color:#2a3a55; color:#94a3b8; }
</style>

<script nonce="{{ csp_nonce() }}">
(function () {
    var SAVE_URL = "{{ route('instructor.certificate-builder.item.update') }}";
    var RESET_URL = "{{ route('instructor.certificate-builder.reset-layout') }}";
    var TOKEN = "{{ csrf_token() }}";
    var $ = function (s) { return document.querySelector(s); };
    var canvas = $('#cbCanvas');
    var stageClassic = $('#cbStageClassic'), stageEnt = $('#cbStageEnt'), entEl = $('#cbEnt');
    var pill = $('#cbSaved');

    // Realistic sample data — fills the preview + the [placeholder] tags so the
    // coach sees a lifelike certificate instead of raw {student_name} etc.
    var SAMPLE = {
        student: @json($cbSample['student']), course: @json($cbSample['course']),
        date: @json($cbSample['date']), instructor: @json($coachName), platform: @json($brandName)
    };

    // ── Classic canvas mirrors the form live (with sample fallback) ──
    function fillTags(v, fallback) {
        v = (v || '').trim(); if (!v) v = fallback || '';
        return v.replace(/\[student_name\]/gi, SAMPLE.student).replace(/\[course\]/gi, SAMPLE.course)
                .replace(/\[date\]/gi, SAMPLE.date).replace(/\[instructor_name\]/gi, SAMPLE.instructor)
                .replace(/\[platform_name\]/gi, SAMPLE.platform);
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function applyClassic() {
        if (!canvas) return;
        var t = ($('[name=title]') || {}).value, s = ($('[name=sub_title]') || {}).value, d = ($('[name=description]') || {}).value;
        $('#title').textContent = fillTags(t, 'Certificate of Achievement');
        $('#sub_title').textContent = fillTags(s, SAMPLE.course);
        $('#description').innerHTML = esc(fillTags(d, 'This certificate is awarded to ' + SAMPLE.student + ' for successfully completing ' + SAMPLE.course + '.')).replace(/\n/g, '<br>');
    }
    ['title', 'sub_title', 'description'].forEach(function (n) {
        var el = $('[name=' + n + ']'); if (el) el.addEventListener('input', applyClassic);
    });
    applyClassic();

    // ── Live image previews (background + signature) via FileReader ──
    function readImg(input, cb) {
        var f = input.files && input.files[0]; if (!f) return;
        var r = new FileReader(); r.onload = function (e) { cb(e.target.result); }; r.readAsDataURL(f);
    }
    var bgInput = $('input[name=background]'), sigInput = $('input[name=signature]');
    if (bgInput) bgInput.addEventListener('change', function () { readImg(bgInput, function (u) { if (canvas) canvas.style.backgroundImage = "url('" + u + "')"; }); });
    if (sigInput) sigInput.addEventListener('change', function () {
        readImg(sigInput, function (u) {
            var c = $('#cbSigImg'); if (c) { c.src = u; c.style.display = ''; }
            var e = $('#cbEntSig'); if (e) { e.src = u; e.style.display = ''; }
            var en = $('#cbEntSigName'); if (en) en.style.display = 'none';
        });
    });

    // ── Fit the ACTIVE preview to the container ──
    var scale = 1, savedTimer = null;
    function fit() {
        if (stageEnt && !stageEnt.hidden && entEl) {
            var se = stageEnt.clientWidth / 1000; entEl.style.transform = 'scale(' + se + ')'; stageEnt.style.height = (707 * se) + 'px';
        }
        if (stageClassic && !stageClassic.hidden && canvas) {
            scale = stageClassic.clientWidth / 930; canvas.style.transform = 'scale(' + scale + ')'; stageClassic.style.height = (600 * scale) + 'px';
        }
    }
    fit();
    window.addEventListener('resize', fit);

    // ── Instant template switch ──
    var badge = $('#cbStyleBadge'), dim = $('#cbDim'), foot = $('#cbFoot'), entOpts = $('#cbEntOpts');
    function switchStyle(val) {
        var ent = (val !== 'classic');
        // Keep the selected-card highlight in sync (fallback for browsers without :has).
        document.querySelectorAll('.cb-style__opt').forEach(function (o) { o.classList.remove('is-on'); });
        var chk = document.querySelector('input[name=certificate_style]:checked');
        if (chk) chk.closest('.cb-style__opt').classList.add('is-on');
        if (entOpts) entOpts.hidden = !ent; // design gallery + accent only for Enterprise
        if (stageEnt) stageEnt.hidden = !ent;
        if (stageClassic) stageClassic.hidden = ent;
        if (badge) badge.textContent = ent ? @json(__('Enterprise')) : @json(__('Classic'));
        if (dim) dim.textContent = ent ? 'A4 landscape' : '930 × 600';
        if (pill) pill.hidden = ent;
        var resetBtn = $('#cbResetLayout'); if (resetBtn) resetBtn.hidden = ent; // classic-only
        if (foot) foot.textContent = ent
            ? @json(__('Enterprise uses your brand colour, logo & signature. The exported PDF is A4 landscape and matches this layout.'))
            : @json(__('Scaled to fit — the exported certificate is 930 × 600 px and matches this layout.'));
        requestAnimationFrame(fit);
    }
    document.querySelectorAll('input[name=certificate_style]').forEach(function (r) {
        r.addEventListener('change', function () { if (r.checked) switchStyle(r.value); });
    });

    // ── Enterprise DESIGN gallery: live-switch the preview skin ──
    document.querySelectorAll('input[name=certificate_template]').forEach(function (r) {
        r.addEventListener('change', function () {
            if (!r.checked) return;
            if (entEl) entEl.setAttribute('data-tpl', r.value);
            document.querySelectorAll('.cb-tpl__opt').forEach(function (o) { o.classList.remove('is-on'); });
            r.closest('.cb-tpl__opt').classList.add('is-on');
        });
    });

    // ── Accent colour: recolour the preview + thumbnails live (blank = brand) ──
    var accPick = $('#cbAccentPick'), accHex = $('#cbAccentHex'), accReset = $('#cbAccentReset');
    var tplWrap = document.querySelector('.cb-tpl');
    var BRAND = accReset ? accReset.getAttribute('data-brand') : '{{ $brandColor }}';
    function isHex(v) { return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v); }
    function applyAccent(v, syncPicker) {
        var col = isHex(v) ? v : BRAND; // fall back to brand when blank/invalid
        if (entEl) entEl.style.setProperty('--cb-brand', col);
        if (tplWrap) tplWrap.style.setProperty('--cb-thumb', col);
        if (syncPicker && accPick && isHex(col)) accPick.value = col.length === 4
            ? '#' + col[1] + col[1] + col[2] + col[2] + col[3] + col[3] : col;
    }
    if (accPick) accPick.addEventListener('input', function () {
        if (accHex) accHex.value = accPick.value;
        applyAccent(accPick.value, false);
    });
    if (accHex) accHex.addEventListener('input', function () { applyAccent(accHex.value.trim(), true); });
    if (accReset) accReset.addEventListener('click', function () {
        if (accHex) accHex.value = '';       // blank ⇒ server stores null ⇒ brand colour
        if (accPick) accPick.value = BRAND;
        applyAccent('', false);
    });
    applyAccent(accHex ? accHex.value.trim() : '', true);

    // ── Typography: font style (mirrors the DomPDF family in the export) ──
    var fontInput = $('#cbFontInput');
    document.querySelectorAll('#cbFontSeg .cb-seg__b').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#cbFontSeg .cb-seg__b').forEach(function (x) { x.classList.remove('is-on'); });
            b.classList.add('is-on');
            if (entEl) entEl.style.setProperty('--cb-font', b.getAttribute('data-face'));
            if (fontInput) fontInput.value = b.getAttribute('data-font');
        });
    });

    // ── Typography: text alignment ──
    var alignInput = $('#cbAlignInput');
    document.querySelectorAll('#cbAlignSeg .cb-seg__b').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#cbAlignSeg .cb-seg__b').forEach(function (x) { x.classList.remove('is-on'); });
            b.classList.add('is-on');
            if (entEl) entEl.setAttribute('data-al', b.getAttribute('data-align'));
            if (alignInput) alignInput.value = b.getAttribute('data-align');
        });
    });

    // ── Background: paper colour (override wins; otherwise the design default) ──
    var PAPER_DEFAULTS = { classic: '#faf7f0', modern: '#ffffff', royal: '#fbf6ea' };
    var paperInput = $('#cbPaperInput'), paperPick = $('#cbPaperPick'), paperReset = $('#cbPaperReset');
    var paperOverride = (paperInput && paperInput.value) ? paperInput.value : null;
    function curDesign() { var on = document.querySelector('input[name=certificate_template]:checked'); return on ? on.value : 'classic'; }
    function applyPaper() {
        var col = paperOverride || PAPER_DEFAULTS[curDesign()] || '#faf7f0';
        if (entEl) entEl.style.setProperty('--cb-paper', col);
        document.querySelectorAll('#cbTones .cb-tone').forEach(function (t) {
            t.classList.toggle('is-on', !!paperOverride && t.getAttribute('data-c').toLowerCase() === paperOverride.toLowerCase());
        });
        if (paperPick) paperPick.value = col;
    }
    document.querySelectorAll('#cbTones .cb-tone').forEach(function (t) {
        t.addEventListener('click', function () { paperOverride = t.getAttribute('data-c'); if (paperInput) paperInput.value = paperOverride; applyPaper(); });
    });
    if (paperPick) paperPick.addEventListener('input', function () { paperOverride = paperPick.value; if (paperInput) paperInput.value = paperOverride; applyPaper(); });
    if (paperReset) paperReset.addEventListener('click', function () { paperOverride = null; if (paperInput) paperInput.value = ''; applyPaper(); });
    // Default follows the design when there's no explicit override.
    document.querySelectorAll('input[name=certificate_template]').forEach(function (r) {
        r.addEventListener('change', function () { if (r.checked) applyPaper(); });
    });
    applyPaper();

    // ── Preview sample data (NOT saved — updates the live preview only) ──
    var SMP_DEF = { student: @json($cbSample['student']), course: @json($cbSample['course']),
                    instr: @json($coachName), brand: @json($brandName),
                    date: @json($cbSample['date']), cert: @json($cbSample['certId']) };
    function setText(id, v) { var e = $(id); if (e) e.textContent = v; }
    function bindSample(id, fn) { var e = $(id); if (e) e.addEventListener('input', function () { fn((e.value || '').trim()); }); }
    bindSample('#cbSmpStudent', function (v) { v = v || SMP_DEF.student; SAMPLE.student = v; setText('#cbEntName', v); applyClassic(); });
    bindSample('#cbSmpCourse', function (v) { v = v || SMP_DEF.course; SAMPLE.course = v; setText('#cbEntCourse', v); applyClassic(); });
    bindSample('#cbSmpInstr', function (v) {
        v = v || SMP_DEF.instr; SAMPLE.instructor = v;
        setText('#cbEntInstr', v); setText('#cbEntSigName', v); applyClassic();
    });
    bindSample('#cbSmpBrand', function (v) {
        v = v || SMP_DEF.brand; SAMPLE.platform = v;
        setText('#cbEntBrand', v); setText('#cbEntBrand2', v); setText('#cbEntBrand3', v);
        setText('#cbEntSealM', (v.charAt(0) || 'A').toUpperCase()); applyClassic();
    });
    bindSample('#cbSmpDate', function (v) { setText('#cbEntDate', v || SMP_DEF.date); });
    bindSample('#cbSmpCert', function (v) { setText('#cbEntCred', v || SMP_DEF.cert); });

    // ── Device preview width ──
    var frameEl = document.querySelector('.cb-frame');
    if (frameEl) frameEl.classList.add('dev-desktop');
    document.querySelectorAll('#cbDevices .cb-dev').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#cbDevices .cb-dev').forEach(function (x) { x.classList.remove('is-on'); });
            b.classList.add('is-on');
            if (frameEl) { frameEl.classList.remove('dev-desktop', 'dev-tablet', 'dev-mobile'); frameEl.classList.add('dev-' + b.getAttribute('data-dev')); }
            requestAnimationFrame(fit);
        });
    });

    // ── Reset layout: restore clean, non-overlapping default positions ──
    var DEFAULT_POS = { title: {x:0,y:210}, sub_title: {x:0,y:245}, description: {x:0,y:281}, signature: {x:398,y:392} };
    var resetLayoutBtn = $('#cbResetLayout');
    if (resetLayoutBtn) resetLayoutBtn.addEventListener('click', function () {
        resetLayoutBtn.disabled = true;
        // Apply immediately in the preview (text rows are centred, so only top moves).
        Object.keys(DEFAULT_POS).forEach(function (id) {
            var el = $('#' + id); if (!el) return;
            el.style.top = DEFAULT_POS[id].y + 'px';
            if (id === 'signature') el.style.left = DEFAULT_POS[id].x + 'px';
        });
        fetch(RESET_URL, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: new URLSearchParams({ _token: TOKEN })
        }).then(function () { flashSaved(); }).catch(function () {}).finally(function () { resetLayoutBtn.disabled = false; });
    });

    function flashSaved() {
        if (!pill) return;
        pill.classList.add('is-saved');
        pill.innerHTML = '<i class="bi bi-check2"></i> {{ __('Saved') }}';
        clearTimeout(savedTimer);
        savedTimer = setTimeout(function () {
            pill.classList.remove('is-saved');
            pill.innerHTML = '<i class="bi bi-arrows-move"></i> {{ __('Drag to position') }}';
        }, 1600);
    }

    // ── Classic drag-to-position (only meaningful for the Classic template) ──
    document.querySelectorAll('.cb-el').forEach(function (el) {
        // Text rows render horizontally CENTRED in the PDF, so only the
        // signature is freely positioned on both axes.
        var freeX = (el.id === 'signature');

        el.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            var startX = e.clientX, startY = e.clientY;
            var origLeft = freeX ? (parseFloat(el.style.left) || 0) : 0;
            var origTop  = parseFloat(el.style.top) || 0;

            function move(ev) {
                if (freeX) {
                    var x = origLeft + (ev.clientX - startX) / scale;
                    x = Math.max(0, Math.min(x, 930 - el.offsetWidth));
                    el.style.left = x + 'px';
                }
                var y = origTop + (ev.clientY - startY) / scale;
                y = Math.max(0, Math.min(y, 600 - el.offsetHeight));
                el.style.top = y + 'px';
            }
            function up() {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', up);
                fetch(SAVE_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                    body: new URLSearchParams({
                        _token: TOKEN,
                        element_id: el.id,
                        x_position: freeX ? Math.round(parseFloat(el.style.left) || 0) : 0,
                        y_position: Math.round(parseFloat(el.style.top) || 0)
                    })
                }).then(function () { flashSaved(); }).catch(function () {});
            }
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
        });
    });
})();
</script>
@endsection
