{{--
    Course wizard stepper. 2026-07-04: restyled from Bootstrap nav-tabs to a
    corp numbered stepper. Functional hooks preserved 1:1 — each step is still a
    `button.navigation-btn.nav-link[data-step]` with its original id + active
    logic, and the submit-on-click script below is unchanged, so courses.js and
    all four step views keep working.
--}}
@php
    $wizardSteps = [
        ['pos' => 1, 'step' => 1, 'id' => 'itemOne-tab',   'label' => __('Basic Infos')],
        ['pos' => 2, 'step' => 2, 'id' => 'itemTwo-tab',   'label' => __('More Infos')],
        ['pos' => 3, 'step' => 3, 'id' => 'itemThree-tab', 'label' => __('Course Contents')],
        ['pos' => 4, 'step' => 5, 'id' => 'itemFour-five', 'label' => __('Finish')],
    ];
    $onFirst = \Illuminate\Support\Facades\Route::is('instructor.courses.create')
             || \Illuminate\Support\Facades\Route::is('instructor.courses.edit-view');
    $reqStep = (int) request('step');
    $activePos = ($onFirst || $reqStep === 1) ? 1
               : ($reqStep === 2 ? 2 : ($reqStep === 3 ? 3 : ($reqStep === 5 ? 4 : 1)));
@endphp

<div class="course-stepper">
    <ol class="cs-track">
        @foreach ($wizardSteps as $s)
            @php
                $isActive = $s['pos'] === $activePos;
                $isDone   = $s['pos'] < $activePos;
            @endphp
            <li class="cs-item {{ $isActive ? 'is-active' : '' }} {{ $isDone ? 'is-done' : '' }}">
                <button type="button"
                        class="nav-link navigation-btn cs-btn {{ $isActive ? 'active' : '' }}"
                        id="{{ $s['id'] }}" data-step="{{ $s['step'] }}">
                    <span class="cs-num">
                        @if ($isDone)<i class="fas fa-check"></i>@else{{ $s['pos'] }}@endif
                    </span>
                    <span class="cs-meta">
                        <span class="cs-kicker">{{ __('Step') }} {{ $s['pos'] }}</span>
                        <span class="cs-label">{{ $s['label'] }}</span>
                    </span>
                </button>
            </li>
        @endforeach
    </ol>
</div>

<style>
    .course-stepper { margin: 4px 0 20px; }
    .course-stepper .cs-track {
        list-style: none; margin: 0; padding: 10px; display: flex; gap: 6px;
        background: #fff; border: 1px solid var(--corp-line, #e8ecf2); border-radius: 14px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.7), 0 1px 2px rgba(15,23,42,.04);
        overflow-x: auto;
    }
    .course-stepper .cs-item { flex: 1 1 0; min-width: 150px; position: relative; }
    .course-stepper .cs-item:not(:last-child)::after {
        content: ""; position: absolute; top: 50%; right: -3px; width: 6px; height: 2px;
        background: var(--corp-line, #e8ecf2); transform: translateY(-50%);
    }
    .course-stepper .cs-btn {
        width: 100%; display: flex; align-items: center; gap: 11px;
        padding: 11px 14px; border: 1px solid transparent; border-radius: 10px;
        background: transparent; cursor: pointer; text-align: left;
        transition: background .15s, border-color .15s;
    }
    .course-stepper .cs-btn:hover { background: var(--corp-brand-bg, #ecfdf5); }
    .course-stepper .cs-num {
        width: 30px; height: 30px; flex-shrink: 0; border-radius: 9px;
        display: grid; place-items: center; font-size: 13px; font-weight: 800;
        background: #f1f5f9; color: #94a3b8; border: 1px solid #e8ecf2;
        font-variant-numeric: tabular-nums; transition: all .15s;
    }
    .course-stepper .cs-meta { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
    .course-stepper .cs-kicker {
        font-size: 9.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #b6c0cc;
    }
    .course-stepper .cs-label {
        font-size: 13.5px; font-weight: 700; color: #64748b;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .course-stepper .cs-item.is-done .cs-num {
        background: var(--corp-brand-bg, #ecfdf5); color: var(--corp-brand-deep, #059669);
        border-color: var(--corp-brand-border, #a7f3d0);
    }
    .course-stepper .cs-item.is-done .cs-label { color: #334155; }
    .course-stepper .cs-item.is-done::after { background: var(--corp-brand-border, #a7f3d0); }
    .course-stepper .cs-item.is-active .cs-btn {
        background: var(--corp-brand-bg, #ecfdf5); border-color: var(--corp-brand-border, #a7f3d0);
    }
    .course-stepper .cs-item.is-active .cs-num {
        background: var(--corp-brand, #10b981); color: #fff; border-color: transparent;
        box-shadow: 0 4px 12px -3px rgba(16,185,129,.5);
    }
    .course-stepper .cs-item.is-active .cs-kicker { color: var(--corp-brand, #10b981); }
    .course-stepper .cs-item.is-active .cs-label { color: var(--corp-brand-deep, #059669); }
    @media (max-width: 680px) {
        .course-stepper .cs-kicker { display: none; }
        .course-stepper .cs-item { min-width: 120px; }
    }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .course-stepper .cs-track { background: #1e293b; box-shadow: none; }
    html[data-theme="dark"] .course-stepper .cs-num   { background: #22304a; border-color: #2a3a55; }
    html[data-theme="dark"] .course-stepper .cs-label { color: #94a3b8; }
</style>

@push('scripts')
    <script>
        $(document).ready(function() {
            $('.navigation-btn').on('click', function() {
                let step = $(this).data('step');
                $('.course-form').find('input[name="next_step"]').val(step);
                $('.course-form').trigger('submit');
            })
        })
    </script>
@endpush
