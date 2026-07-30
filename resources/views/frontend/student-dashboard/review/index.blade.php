@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Student reviews — corporate redesign (2026-05).

        Inter font, brand-aware via $brand. Replaces the dark Sora-themed
        layout with the platform corp tokens. Adds a per-row delete action
        that hits the existing AJAX endpoint (route name kept).

        Functional contract preserved 1:1:
          * $reviews paginator with course (id,title) relation
          * route('student.reviews.show', $review->id) link
          * route('student.reviews.destroy', $review->id) — same legacy
            JSON endpoint (now wired up as a delete button instead of
            commented out)
          * Pagination via $reviews->links()
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        .rv-page {
            --rv-brand:       {{ $brand->primaryColor ?: '#10b981' }};
            --rv-brand-2:     {{ $brand->accentColor  ?: '#059669' }};
            --rv-text:        #0b1220;
            --rv-text-2:      #1f2937;
            --rv-muted:       #6b7280;
            --rv-subtle:      #9ca3af;
            --rv-line:        #e5e7eb;
            --rv-line-soft:   #f1f3f5;
            --rv-bg:          #fafbfc;
            --rv-card:        #ffffff;
            --rv-shadow-sm:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 2px 6px -2px rgba(15, 23, 42, 0.04);
            --rv-ease: cubic-bezier(.22, 1, .36, 1);

            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding: 4px 8px 28px 0;       /* right gutter clears the floating top-right toolbar */
            color: var(--rv-text);
        }
        @media (max-width: 768px) { .rv-page { padding: 4px 0 20px; } }
        .rv-page *, .rv-page *::before, .rv-page *::after { box-sizing: border-box; }
        .rv-page .rv-tabular {
            font-variant-numeric: tabular-nums; font-feature-settings: 'tnum';
        }

        .rv-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            gap: 16px; flex-wrap: wrap; margin-bottom: 22px;
        }
        .rv-eyebrow {
            font-size: 11px; font-weight: 700; color: var(--rv-brand);
            text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 6px;
        }
        .rv-header h1 {
            font-size: 24px; font-weight: 800; color: var(--rv-text);
            margin: 0 0 4px; letter-spacing: -0.028em; line-height: 1.2;
        }
        .rv-header__sub {
            font-size: 13.5px; color: var(--rv-muted); margin: 0; line-height: 1.5;
        }

        /* KPI strip */
        .rv-kpi-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 14px; margin-bottom: 22px;
        }
        @media (max-width: 700px) { .rv-kpi-grid { grid-template-columns: 1fr; } }
        .rv-kpi {
            background: var(--rv-card); border: 1px solid var(--rv-line);
            border-radius: 12px; padding: 16px 18px;
            box-shadow: var(--rv-shadow-sm);
            display: flex; align-items: center; gap: 14px;
            position: relative;
        }
        .rv-kpi::before {
            content: ''; position: absolute; top: 0; left: 18px; right: 18px;
            height: 2px; border-radius: 0 0 2px 2px;
            background: var(--rv-accent, var(--rv-brand)); opacity: 0.85;
        }
        .rv-kpi__icon {
            width: 38px; height: 38px; border-radius: 10px;
            background: color-mix(in srgb, var(--rv-accent, var(--rv-brand)) 12%, #fff);
            color: var(--rv-accent, var(--rv-brand));
            display: flex; align-items: center; justify-content: center; font-size: 14px;
            border: 1px solid color-mix(in srgb, var(--rv-accent, var(--rv-brand)) 18%, transparent);
            flex-shrink: 0;
        }
        .rv-kpi__body { flex: 1; min-width: 0; }
        .rv-kpi__label {
            font-size: 11px; font-weight: 700; color: var(--rv-muted);
            text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;
        }
        .rv-kpi__value {
            font-size: 22px; font-weight: 800; color: var(--rv-text);
            line-height: 1.05; letter-spacing: -0.025em;
        }

        /* Card list */
        .rv-list {
            display: flex; flex-direction: column; gap: 12px;
        }
        .rv-card {
            background: var(--rv-card); border: 1px solid var(--rv-line);
            border-radius: 12px; padding: 16px 18px;
            box-shadow: var(--rv-shadow-sm);
            display: flex; align-items: center; gap: 16px;
            transition: border-color .15s var(--rv-ease), box-shadow .15s var(--rv-ease);
        }
        .rv-card:hover {
            border-color: color-mix(in srgb, var(--rv-brand) 30%, var(--rv-line));
            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 6px 18px -6px rgba(15, 23, 42, 0.08);
        }
        .rv-num {
            width: 36px; height: 36px; border-radius: 9px;
            background: var(--rv-line-soft); color: var(--rv-muted);
            display: flex; align-items: center; justify-content: center;
            font-size: 12.5px; font-weight: 700;
            flex-shrink: 0; font-variant-numeric: tabular-nums;
        }
        .rv-body { flex: 1; min-width: 0; }
        .rv-title {
            font-size: 14px; font-weight: 600; color: var(--rv-text);
            margin: 0 0 6px; line-height: 1.35;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .rv-meta {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .rv-stars {
            display: inline-flex; gap: 2px;
        }
        .rv-stars i { font-size: 12px; }
        .rv-stars .on  { color: #f59e0b; }
        .rv-stars .off { color: var(--rv-line); }
        .rv-rating-num {
            font-size: 12px; color: var(--rv-text-2); font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .rv-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 999px;
            font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .rv-badge::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%;
            background: currentColor;
        }
        .rv-badge--approved { background: #ecfdf5; color: #047857; }
        .rv-badge--pending  { background: #fffbeb; color: #92400e; }

        .rv-actions {
            display: inline-flex; gap: 6px; flex-shrink: 0;
        }
        .rv-action {
            width: 32px; height: 32px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; font-size: 12px;
            transition: all .15s var(--rv-ease);
            cursor: pointer; border: 0; font-family: inherit;
        }
        .rv-action--view {
            background: color-mix(in srgb, var(--rv-brand) 10%, #fff);
            color: var(--rv-brand);
            border: 1px solid color-mix(in srgb, var(--rv-brand) 18%, transparent);
        }
        .rv-action--view:hover {
            background: var(--rv-brand); color: #fff; text-decoration: none;
        }
        .rv-action--delete {
            background: #fef2f2; color: #dc2626;
            border: 1px solid #fecaca;
        }
        .rv-action--delete:hover {
            background: #dc2626; color: #fff;
        }

        /* Empty */
        .rv-empty {
            text-align: center; padding: 60px 24px;
            background: var(--rv-card); border: 1px dashed var(--rv-line);
            border-radius: 14px;
        }
        .rv-empty__icon {
            width: 56px; height: 56px; margin: 0 auto 14px; border-radius: 14px;
            background: color-mix(in srgb, var(--rv-brand) 10%, #fff);
            color: var(--rv-brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            border: 1px solid color-mix(in srgb, var(--rv-brand) 18%, transparent);
        }
        .rv-empty__title {
            font-size: 16px; font-weight: 700; color: var(--rv-text);
            margin-bottom: 6px; letter-spacing: -0.015em;
        }
        .rv-empty__text {
            font-size: 13.5px; color: var(--rv-muted); line-height: 1.5; margin-bottom: 16px;
        }

        /* Pagination */
        .rv-pagination {
            display: flex; justify-content: center; margin-top: 22px;
        }
        .rv-pagination .pagination { gap: 4px; margin: 0; }
        .rv-pagination .page-item .page-link {
            background: var(--rv-card); border: 1px solid var(--rv-line);
            color: var(--rv-text-2); border-radius: 8px !important;
            padding: 7px 13px; font-size: 12.5px; font-weight: 600;
            font-family: inherit; transition: all .15s var(--rv-ease);
        }
        .rv-pagination .page-item .page-link:hover {
            border-color: var(--rv-brand); color: var(--rv-brand);
        }
        .rv-pagination .page-item.active .page-link {
            background: var(--rv-brand); border-color: var(--rv-brand); color: #fff;
        }
        .rv-pagination .page-item.disabled .page-link {
            color: var(--rv-subtle); background: var(--rv-line-soft);
        }

        @media (max-width: 600px) {
            .rv-card { flex-wrap: wrap; }
            .rv-body { min-width: 100%; }
        }
    </style>

    <style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .rv-page {
        --rv-text: #e2e8f0;
        --rv-text-2: #cbd5e1;
        --rv-muted: #94a3b8;
        --rv-subtle: #94a3b8;
        --rv-line: #2a3a55;
        --rv-line-soft: #22304a;
        --rv-bg: #17233a;
        --rv-card: #1e293b;
        --rv-shadow-sm: none;
    }
    </style>

    @php
        // 2026-05-26 (bug-doc S6) — Previously this tried (clone $reviews
        // ->getQuery())->where(...)->count(). $reviews is a LengthAware
        // Paginator → __call proxies to its Collection items → Collection
        // has no getQuery() → 500 every time the review page loads.
        // Fixed by querying CourseReview directly with the same user-id
        // scope. Two cheap COUNT() queries, no Builder cloning gymnastics.
        $studentId = auth('web')->id();
        $total     = $reviews->total() ?? $reviews->count();
        $approved  = \App\Models\CourseReview::where('user_id', $studentId)->where('status', 1)->count();
        $pending   = \App\Models\CourseReview::where('user_id', $studentId)->where('status', '!=', 1)->count();

        // Average rating across THIS page (cheap, no extra query).
        $avgRating = $reviews->count() > 0 ? $reviews->sum('rating') / $reviews->count() : 0;
    @endphp

    <div class="rv-page">

        <header class="rv-header">
            <div>
                <div class="rv-eyebrow">{{ __('My feedback') }}</div>
                <h1>{{ __('My reviews') }}</h1>
                <p class="rv-header__sub">
                    {{ __('Every rating and comment you have left on courses you bought.') }}
                </p>
            </div>
        </header>

        <div class="rv-kpi-grid">
            <div class="rv-kpi" style="--rv-accent: var(--rv-brand);">
                <span class="rv-kpi__icon"><i class="fas fa-comment-dots"></i></span>
                <div class="rv-kpi__body">
                    <div class="rv-kpi__label">{{ __('Total reviews') }}</div>
                    <div class="rv-kpi__value rv-tabular">{{ $total }}</div>
                </div>
            </div>
            <div class="rv-kpi" style="--rv-accent: #10b981;">
                <span class="rv-kpi__icon"><i class="fas fa-check-circle"></i></span>
                <div class="rv-kpi__body">
                    <div class="rv-kpi__label">{{ __('Approved') }}</div>
                    <div class="rv-kpi__value rv-tabular">{{ $approved }}</div>
                </div>
            </div>
            <div class="rv-kpi" style="--rv-accent: #f59e0b;">
                <span class="rv-kpi__icon"><i class="fas fa-star"></i></span>
                <div class="rv-kpi__body">
                    <div class="rv-kpi__label">{{ __('Avg rating') }}</div>
                    <div class="rv-kpi__value rv-tabular">{{ number_format($avgRating, 1) }}</div>
                </div>
            </div>
        </div>

        @if ($reviews->count() > 0)
            <div class="rv-list">
                @foreach ($reviews as $index => $review)
                    @php
                        $rowNum = ($reviews->currentPage() - 1) * $reviews->perPage() + $index + 1;
                        $rating = (int) $review->rating;
                        $isApproved = $review->status == 1;
                    @endphp
                    <div class="rv-card">
                        <span class="rv-num">{{ $rowNum }}</span>
                        <div class="rv-body">
                            <div class="rv-title" title="{{ $review->course->title ?? '' }}">
                                {{ $review->course->title ?? __('Course unavailable') }}
                            </div>
                            <div class="rv-meta">
                                <div class="rv-stars" aria-label="{{ $rating }}/5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="fas fa-star {{ $i <= $rating ? 'on' : 'off' }}"></i>
                                    @endfor
                                </div>
                                <span class="rv-rating-num">{{ $rating }}.0</span>
                                <span class="rv-badge {{ $isApproved ? 'rv-badge--approved' : 'rv-badge--pending' }}">
                                    {{ $isApproved ? __('Approved') : __('Pending') }}
                                </span>
                            </div>
                        </div>
                        <div class="rv-actions">
                            <a href="{{ route('student.reviews.show', $review->id) }}"
                               class="rv-action rv-action--view"
                               title="{{ __('View review') }}">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button type="button"
                                    class="rv-action rv-action--delete rv-delete-btn"
                                    data-id="{{ $review->id }}"
                                    data-url="{{ route('student.reviews.destroy', $review->id) }}"
                                    title="{{ __('Delete review') }}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($reviews->hasPages())
                <div class="rv-pagination">{{ $reviews->links() }}</div>
            @endif
        @else
            <div class="rv-empty">
                <div class="rv-empty__icon"><i class="far fa-comment-dots"></i></div>
                <div class="rv-empty__title">{{ __('No reviews yet') }}</div>
                <div class="rv-empty__text">
                    {{ __("Once you finish a course, you can rate it and your review will appear here.") }}
                </div>
            </div>
        @endif

    </div>

    <script>
        // Delete handler — hits the existing JSON endpoint with no extra
        // dependency. Honours the legacy controller contract:
        //   GET /student/reviews-delete/{id} → returns {status, message}
        (function () {
            document.querySelectorAll('.rv-delete-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!confirm(@json(__('Delete this review? This cannot be undone.')))) return;
                    var url  = btn.getAttribute('data-url');
                    var card = btn.closest('.rv-card');
                    btn.disabled = true;
                    btn.style.opacity = '.6';
                    fetch(url, {
                        method: 'GET',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r.json().catch(function () { return {status: 'success'}; }); })
                    .then(function (data) {
                        if (card) {
                            card.style.transition = 'opacity .25s, transform .25s';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(-8px)';
                            setTimeout(function () { card.remove(); }, 250);
                        }
                        if (window.toastr && data && data.message) {
                            window.toastr.success(data.message);
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.style.opacity = '';
                        alert(@json(__('Could not delete the review. Please try again.')));
                    });
                });
            });
        })();
    </script>
@endsection
