@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

        :root {
            --bg-main-base: #f5f7fc;
            --bg-base: #0d0f1a;
            --bg-card: #d9e5fd;
            --bg-row: #181c30;
            --bg-row-alt: #14172a;
            --border: rgba(255, 255, 255, 0.06);
            --accent: #6c63ff;
            --accent-glow: rgba(108, 99, 255, 0.25);
            --accent-2: #00d4aa;
            --text-primary: #7b82bf;
            --text-secondary: #7b82a8;
            --text-muted: #454b6a;
            --success: #00d4aa;
            --success-bg: rgba(0, 212, 170, 0.1);
            --warning: #f5a623;
            --warning-bg: rgba(245, 166, 35, 0.1);
            --danger: #ff5c7a;
            --danger-bg: rgba(255, 92, 122, 0.1);
            --info: #38bdf8;
            --info-bg: rgba(56, 189, 248, 0.1);
            --shadow: 0 8px 40px rgba(0, 0, 0, 0.5);
            --deading-color: #0f1623;
        }

        .oh-wrapper {
            font-family: 'Sora', sans-serif;
            background: var(--bg-main-base);
            min-height: 100vh;
            padding: 32px 28px;
            color: var(--text-primary);
            border-radius: 5px;
        }

        /* ─── Header ─────────────────────────────── */
        .oh-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .oh-header-left h2 {
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin: 0 0 4px;
            /* color: var(--deading-color); */
            background: var(--deading-color);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .oh-header-left p {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin: 0;
            letter-spacing: 0.02em;
        }

        .oh-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .oh-stat-pill {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: border-color 0.2s, color 0.2s;
        }

        .oh-stat-pill:hover {
            border-color: var(--accent);
            color: var(--text-primary);
        }

        .oh-stat-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        /* ─── Search / Filter bar ─────────────────── */
        .oh-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .oh-search {
            flex: 1;
            min-width: 220px;
            position: relative;
        }

        .oh-search input {
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 16px 10px 40px;
            color: var(--text-primary);
            font-family: 'Sora', sans-serif;
            font-size: 0.82rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            border: 1px solid var(--bg-card);
        }

        .oh-search input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .oh-search input::placeholder {
            color: var(--text-muted);
        }

        .oh-search-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
            pointer-events: none;
        }




        /* ─── Table card ──────────────────────────── */
        .oh-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 18px;
            /* 2026-06-01 responsive audit: this card holds ONLY the
               live-classes table (pagination sits outside it). The table
               has Status / Course / Lesson / Start-time / Join columns
               with nowrap headers, so on phones it is wider than the
               viewport. `overflow:hidden` alone clipped the Join column;
               keep vertical clip for the rounded corners but allow the
               table to swipe horizontally inside the card. */
            overflow: hidden;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            box-shadow: var(--shadow);
        }

        .oh-table {
            width: 100%;
            border-collapse: collapse;
        }

        .oh-table thead tr {
            background: rgba(108, 99, 255, 0.06);
            border-bottom: 1px solid var(--border);
        }

        .oh-table thead th {
            padding: 14px 20px;
            font-size: 0.70rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            color: var(--text-secondary);
            white-space: nowrap;
            user-select: none;
        }

        .oh-table thead th:first-child {
            padding-left: 24px;
        }

        .oh-table thead th:last-child {
            padding-right: 24px;
        }

        .oh-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
            cursor: default;
        }

        .oh-table tbody tr:last-child {
            border-bottom: none;
        }

        .oh-table tbody tr:hover {
            background:#c8d5ff;
        }

        .oh-table tbody td {
            padding: 16px 20px;
            font-size: 0.83rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .oh-table tbody td:first-child {
            padding-left: 24px;
        }

        .oh-table tbody td:last-child {
            padding-right: 24px;
        }

        /* Row number */
        .oh-num {
            width: 32px;
            height: 32px;
            background: rgba(108, 99, 255, 0.08);
            border: 1px solid rgba(108, 99, 255, 0.15);
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        /* Invoice */
        .oh-invoice {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--accent);
            background: var(--accent-glow);
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid rgba(108, 99, 255, 0.2);
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        /* Amount */
        .oh-amount {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-primary);
        }

        .oh-currency {
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-left: 3px;
        }

        /* Gateway */
        .oh-gateway {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 5px 10px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .oh-gateway-icon {
            width: 16px;
            height: 16px;
            object-fit: contain;
            border-radius: 3px;
        }

        /* Badges */
        .oh-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            letter-spacing: 0.03em;
            white-space: nowrap;
            text-transform: capitalize;
        }

        .oh-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
        }

        .oh-badge-completed { 
            color: #6e69f7;
            background: rgb(227 226 255);
            border: 1px solid rgb(205 203 255);
        }

        .oh-badge-completed::before {
            background: var(--success);
            box-shadow: 0 0 4px var(--success);
        }

        .oh-badge-pending {
            color: var(--warning);
            background: var(--warning-bg);
            border: 1px solid rgba(245, 166, 35, 0.2);
        }

        .oh-badge-pending::before {
            background: var(--warning);
            box-shadow: 0 0 4px var(--warning);
        }

        .oh-badge-processing {
            color: var(--info);
            background: var(--info-bg);
            border: 1px solid rgba(56, 189, 248, 0.2);
        }

        .oh-badge-processing::before {
            background: var(--info);
            box-shadow: 0 0 4px var(--info);
        }

        .oh-badge-declined,
        .oh-badge-cancelled {
            color: var(--danger);
            background: var(--danger-bg);
            border: 1px solid rgba(255, 92, 122, 0.2);
        }

        .oh-badge-declined::before,
        .oh-badge-cancelled::before {
            background: var(--danger);
            box-shadow: 0 0 4px var(--danger);
        }

        .oh-badge-paid {
            color: var(--success);
            background: var(--success-bg);
            border: 1px solid rgba(0, 212, 170, 0.2);
        }

        .oh-badge-paid::before {
            background: var(--success);
        }

       
        /* Empty state */
        .oh-empty {
            text-align: center;
            padding: 64px 20px;
        }

        .oh-empty-icon {
            width: 64px;
            height: 64px;
            background: rgba(108, 99, 255, 0.08);
            border: 1px solid rgba(108, 99, 255, 0.15);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 16px;
            color: var(--text-muted);
        }

        .oh-empty h5 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .oh-empty p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Pagination */
        .oh-pagination {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
        }

        .oh-pagination .pagination {
            margin: 0;
            gap: 4px;
        }

        .oh-pagination .page-item .page-link {
            background: var(--bg-row);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            border-radius: 8px !important;
            padding: 6px 13px;
            font-size: 0.78rem;
            font-family: 'Sora', sans-serif;
            font-weight: 500;
            transition: all 0.2s;
        }

        .oh-pagination .page-item.active .page-link,
        .oh-pagination .page-item .page-link:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .oh-wrapper {
                padding: 20px 16px;
            }

            .oh-header-left h2 {
                font-size: 1.3rem;
            }

            .oh-stats {
                display: none;
            }

            .oh-table thead th,
            .oh-table tbody td {
                padding: 12px 14px;
                font-size: 0.78rem;
            }

            .oh-table .hide-mobile {
                display: none;
            }
        }

        /* Entrance animation */
        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .oh-card {
            animation: fadeSlideUp 0.4s ease both;
        }

        .oh-table tbody tr {
            animation: fadeSlideUp 0.35s ease both;
        }

/* live button  */
        .live-btn{
    position: relative;
    display: inline-flex;
    align-items: center;
    gap:6px;
    padding:4px 10px;
    font-size:11px;
    font-weight:600;
    color:#fff;
    background:#6e69f7;
    border-radius:20px;
    text-decoration:none;
    letter-spacing:.3px;
}

/* live dot */
.live-dot{
    width:7px;
    height:7px;
    background:#fff;
    border-radius:50%;
    position:relative;
}

/* pulse animation */
.live-dot::after{
    content:'';
    position:absolute;
    top:0;
    left:0;
    width:7px;
    height:7px;
    border-radius:50%;
    background:#fff;
    animation:livePulse 1.5s infinite;
}

@keyframes livePulse{
    0%{
        transform:scale(1);
        opacity:1;
    }
    70%{
        transform:scale(2.8);
        opacity:0;
    }
    100%{
        opacity:0;
    }
}


    </style>

    <style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] {
        --bg-main-base: #17233a;
        --bg-card: #1e293b;
        --deading-color: #e2e8f0;
    }
    html[data-theme="dark"] .oh-table tbody tr:hover {
        background: #22304a;
    }
    </style>

    <div class="oh-wrapper comman-div-containter">

        {{-- ── Header ── --}}
        <div class="oh-header">
            <div class="oh-header-left">
                <h2> <i class="fas fa-film"></i> {{ __('Live Class Management') }}</h2>
                <p>{{ __('Complete system to manage live teaching sessions and student attendance.') }}</p>
            </div>

            <div class="oh-stats">
                @php
                    $total = $liveClasses->total() ?? $liveClasses->count();
                @endphp
                <div class="oh-stat-pill">
                    <span class="oh-stat-dot" style="background:#6c63ff;box-shadow:0 0 6px #6c63ff"></span>
                    {{ $total }} {{ __('Total') }}
                </div>


            </div>
        </div>

        {{-- ── Toolbar ── --}}
        <div class="oh-toolbar">
            <div class="oh-search">
                <span class="oh-search-icon"><i class="fa fa-search"></i></span>
                <input type="text" id="oh-search-input" placeholder="{{ __('Search by Course...') }}">
            </div>
        </div>

        {{-- ── Table card ── --}}
        <div class="oh-card">
            <table class="oh-table" id="oh-orders-table">
                <thead>
                    <tr>
                        <th>{{ __('No') }}</th>
                        <th>{{ __('Course') }}</th>
                        <th class="hide-mobile">{{ __('Title') }}</th>
                        <th class="hide-mobile-no">{{ __('Start Time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 2026-07-03 — 1:1 Instant Meetings for THIS student (private +
                         tenant-scoped), shown above scheduled classes in the same list. --}}
                    @foreach ($instantMeetings as $im)
                        <tr data-status="instant" data-invoice="{{ strtolower($im->topic ?? '1:1 meeting') }}">
                            <td><span class="oh-num"><i class="fas fa-video"></i></span></td>
                            <td>
                                @if ($im->coach_joined_at)
                                    <a href="{{ route('instant-meeting.room', $im->id) }}" class="live-btn" target="_blank">
                                        <span class="live-dot"></span> {{ __('JOIN') }}
                                    </a>
                                @else
                                    <span class="badge bg-inverse-warning">{{ __('Waiting for coach') }}</span>
                                @endif
                                <span class="badge" style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;">{{ __('1:1 Meeting') }}</span>
                                {{ $im->topic ?: __('1:1 Session') }}
                            </td>
                            <td class="hide-mobile">
                                {{ $im->coach?->name }}@if($im->purpose) · <span style="text-transform:capitalize">{{ $im->purpose }}</span>@endif
                            </td>
                            <td>
                                <span class="badge oh-badge-completed">
                                    <i class="far fa-clock me-1"></i>
                                    {{ optional($im->started_at)->format('h:i A, d M Y') ?: __('Now') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    @forelse ($liveClasses as $index => $live)
                        <tr data-status="{{ $live->status }}"
                            data-invoice="{{ strtolower($live->lesson?->course?->title) }}">

                            {{-- No --}}
                            <td><span class="oh-num">{{ ++$index }}</span></td>

                            {{-- Title --}}
                            <td>
                                {{-- 2026-06-03 (#7) — a finished class must not show a Join
                                     button. CourseLiveClass::isOver() is the single source of
                                     truth (finalised / batch ended / scheduled window + grace).
                                     The server (ZoomSignatureController@issue) enforces the same;
                                     this is the matching UI state. --}}
                                @php $liveStart = $live->start_time ? \Carbon\Carbon::parse($live->start_time) : null; @endphp
                                @if ($live->isOver())
                                    <span class="badge oh-badge-completed">{{ __('Completed') }}</span>
                                @elseif ($liveStart && $liveStart->isToday())

                                    <a href="{{ route('student.learning.live', ['slug' => $live->lesson?->course?->slug, 'lesson_id' => $live?->lesson?->id]) }}"
                                        class="live-btn" target="_blank">
                                            <span class="live-dot"></span>
                                            LIVE
                                        </a>

                                    &nbsp;
                                @else
                                    <span class="badge bg-inverse-warning">{{ __('Scheduled') }}</span>
                                @endif

                                <span class="badge" style="background:#ecfeff;color:#0e7490;border:1px solid #a5f3fc;">{{ __('Live Class') }}</span>
                                {{ $live->lesson?->course?->title ?? 'N/A' }}
                            </td>

                            {{-- Start Time --}}
                            <td class="hide-mobile"> {{ $live?->lesson?->title }}</td>
                            <td> <span class="badge oh-badge-completed">
                                    <i class="far fa-clock me-1"></i>
                                    {{ \Carbon\Carbon::parse($live->start_time)->format('h:i A, d M Y') }}
                                </span>
                            </td>
 
                        </tr>
                    @empty
                        @if ($instantMeetings->isEmpty())
                        <tr>
                            <td colspan="7">
                                <div class="oh-empty">
                                    <div class="oh-empty-icon"><i class="fas fa-video"></i></div>
                                    <h5>{{ __('Nothing scheduled') }}</h5>
                                    <p>{{ __('No live classes or instant meetings are available at the moment.') }}</p>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @endforelse
                </tbody>
            </table>

            @if ($liveClasses->hasPages())
                <div class="oh-pagination">
                    {{ $liveClasses->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
        // Live search filter
        const searchInput = document.getElementById('oh-search-input');
        const rows = document.querySelectorAll('#oh-orders-table tbody tr[data-status]');

        let activeFilter = 'all';

        function applyFilters() {
            const q = searchInput.value.toLowerCase().trim();
            rows.forEach(row => {
                const status = row.dataset.status;
                const invoice = row.dataset.invoice || '';
                const matchFilter = (activeFilter === 'all') || (status === activeFilter);
                const matchSearch = !q || invoice.includes(q);
                row.style.display = (matchFilter && matchSearch) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', applyFilters);

        // Stagger rows on load
        rows.forEach((row, i) => {
            row.style.animationDelay = `${i * 0.04}s`;
        });
    </script>
@endsection
