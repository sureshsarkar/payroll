@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Student wishlist — corporate redesign (2026-05).

        Inter font, brand-aware via $brand. Renders the wishlist-card
        partial which holds the actual data query + remove buttons.

        Functional contract preserved 1:1:
          * @include('frontend.wishlist.wishlist-card') unchanged
          * Loading preloader still rendered
          * Wrapper class .dashboard__content-wrap preserved (layout hook)
    --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <style>
        .wl-page {
            --wl-brand:       {{ $brand->primaryColor ?: '#6366f1' }};
            --wl-brand-2:     {{ $brand->accentColor  ?: '#8b5cf6' }};
            --wl-text:        #0b1220;
            --wl-text-2:      #1f2937;
            --wl-muted:       #6b7280;
            --wl-subtle:      #9ca3af;
            --wl-line:        #e5e7eb;
            --wl-line-soft:   #f1f3f5;
            --wl-bg:          #fafbfc;
            --wl-card:        #ffffff;
            --wl-shadow-sm:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 2px 6px -2px rgba(15, 23, 42, 0.04);
            --wl-shadow-md:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 6px 18px -6px rgba(15, 23, 42, 0.08),
                0 12px 36px -12px rgba(15, 23, 42, 0.08);
            --wl-ease: cubic-bezier(.22, 1, .36, 1);

            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding: 4px 8px 28px 0;       /* right gutter clears the floating top-right toolbar */
            color: var(--wl-text);
        }
        @media (max-width: 768px) { .wl-page { padding: 4px 0 20px; } }
        .wl-page *, .wl-page *::before, .wl-page *::after { box-sizing: border-box; }

        .wl-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            gap: 16px; flex-wrap: wrap; margin-bottom: 22px;
        }
        .wl-eyebrow {
            font-size: 11px; font-weight: 700; color: var(--wl-brand);
            text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 6px;
        }
        .wl-header h1 {
            font-size: 24px; font-weight: 800; color: var(--wl-text);
            margin: 0 0 4px; letter-spacing: -0.028em; line-height: 1.2;
            display: flex; align-items: center; gap: 10px;
        }
        .wl-header h1 i { color: #ef4444; font-size: 22px; }
        .wl-header__sub {
            font-size: 13.5px; color: var(--wl-muted); margin: 0; line-height: 1.5;
        }

        /* ── Card grid (cards live in wishlist-card.blade.php) ── */
        .wl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .wl-card {
            background: var(--wl-card); border: 1px solid var(--wl-line);
            border-radius: 14px; overflow: hidden;
            display: flex; flex-direction: column;
            box-shadow: var(--wl-shadow-sm);
            transition: transform .15s var(--wl-ease),
                        box-shadow .15s var(--wl-ease),
                        border-color .15s var(--wl-ease);
        }
        .wl-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--wl-shadow-md);
            border-color: color-mix(in srgb, var(--wl-brand) 30%, var(--wl-line));
        }
        .wl-thumb {
            position: relative; aspect-ratio: 16 / 9;
            background: var(--wl-line-soft); overflow: hidden;
        }
        .wl-thumb img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .4s var(--wl-ease);
        }
        .wl-card:hover .wl-thumb img { transform: scale(1.04); }

        .wl-price {
            position: absolute; top: 12px; right: 12px;
            display: inline-flex; align-items: center;
            padding: 5px 12px; border-radius: 999px;
            background: #fff; color: var(--wl-brand);
            font-size: 12.5px; font-weight: 700;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.4);
            font-variant-numeric: tabular-nums;
        }
        .wl-price--free {
            background: #10b981; color: #fff; border-color: transparent;
            text-transform: uppercase; letter-spacing: 0.04em;
        }

        .wl-remove {
            position: absolute; bottom: 12px; right: 12px;
            width: 36px; height: 36px; border-radius: 50%;
            background: #fff; color: #ef4444;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
            text-decoration: none;
            transition: all .15s var(--wl-ease);
        }
        .wl-remove:hover {
            background: #ef4444; color: #fff;
            transform: scale(1.08); text-decoration: none;
        }

        .wl-body {
            padding: 16px 18px 14px; flex: 1;
            display: flex; flex-direction: column;
        }
        .wl-cat {
            display: inline-block; align-self: flex-start;
            font-size: 10.5px; font-weight: 700;
            letter-spacing: 0.04em; text-transform: uppercase;
            padding: 3px 9px; border-radius: 5px;
            background: var(--wl-line-soft); color: var(--wl-muted);
            margin-bottom: 8px;
        }
        .wl-title {
            font-size: 14.5px; font-weight: 700; line-height: 1.4;
            margin: 0 0 10px; letter-spacing: -0.01em;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; min-height: 40px;
        }
        .wl-title a { color: var(--wl-text); text-decoration: none; }
        .wl-title a:hover { color: var(--wl-brand); }

        .wl-rating {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 12px; color: var(--wl-muted); margin-bottom: 12px;
        }
        .wl-rating i { color: #f59e0b; font-size: 11px; }
        .wl-rating strong { color: var(--wl-text-2); font-weight: 700; font-variant-numeric: tabular-nums; }

        .wl-meta {
            padding-top: 12px; border-top: 1px solid var(--wl-line-soft);
            display: flex; align-items: center; gap: 14px;
            margin-top: auto;
        }
        .wl-meta__item {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11.5px; color: var(--wl-muted); font-weight: 500;
        }
        .wl-meta__item i { font-size: 11px; color: var(--wl-subtle); }
        .wl-meta__item strong { color: var(--wl-text-2); font-weight: 700; font-variant-numeric: tabular-nums; }

        /* Empty */
        .wl-empty {
            text-align: center; padding: 60px 24px;
            background: var(--wl-card); border: 1px dashed var(--wl-line);
            border-radius: 14px; grid-column: 1 / -1;
        }
        .wl-empty__icon {
            width: 56px; height: 56px; margin: 0 auto 14px; border-radius: 14px;
            background: #fef2f2; color: #ef4444;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            border: 1px solid #fecaca;
        }
        .wl-empty__title {
            font-size: 16px; font-weight: 700; color: var(--wl-text);
            margin-bottom: 6px; letter-spacing: -0.015em;
        }
        .wl-empty__text {
            font-size: 13.5px; color: var(--wl-muted); line-height: 1.5; margin-bottom: 16px;
        }
        .wl-empty__cta {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; border-radius: 9px;
            background: var(--wl-brand); color: #fff;
            font-size: 13px; font-weight: 600; text-decoration: none;
        }
        .wl-empty__cta:hover {
            background: color-mix(in srgb, var(--wl-brand) 88%, #000);
            color: #fff; text-decoration: none;
        }

        /* Pagination */
        .wl-pagination {
            display: flex; justify-content: center; margin-top: 22px;
        }
        .wl-pagination .pagination { gap: 4px; margin: 0; }
        .wl-pagination .page-item .page-link {
            background: var(--wl-card); border: 1px solid var(--wl-line);
            color: var(--wl-text-2); border-radius: 8px !important;
            padding: 7px 13px; font-size: 12.5px; font-weight: 600;
            font-family: inherit; transition: all .15s var(--wl-ease);
        }
        .wl-pagination .page-item .page-link:hover {
            border-color: var(--wl-brand); color: var(--wl-brand);
        }
        .wl-pagination .page-item.active .page-link {
            background: var(--wl-brand); border-color: var(--wl-brand); color: #fff;
        }
        .wl-pagination .page-item.disabled .page-link {
            color: var(--wl-subtle); background: var(--wl-line-soft);
        }
    </style>

    <div class="wl-page">

        <header class="wl-header">
            <div>
                <div class="wl-eyebrow">{{ __('Saved for later') }}</div>
                <h1><i class="fas fa-heart"></i> {{ __('My wishlist') }}</h1>
                <p class="wl-header__sub">
                    {{ __('Courses you bookmarked. Pick them up when you are ready to enroll.') }}
                </p>
            </div>
        </header>

        <div class="preloader-two preloader-two-fixed d-none">
            <div class="loader-icon-two">
                <img src="{{ asset(Cache::get('setting')->preloader) }}" alt="Preloader">
            </div>
        </div>

        <div class="wishlist-content">
            @include('frontend.wishlist.wishlist-card')
        </div>

    </div>
@endsection
