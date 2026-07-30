@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<style>
    /* Kanban board layout — horizontal scroll of columns, each column
       is a vertical scroll of cards. Keeps the board usable on
       narrower screens; the user can swipe-scroll the column row. */
    .lpe-kb-toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .lpe-kb-search { max-width: 360px; }

    .lpe-kb-board {
        display: flex; gap: 14px; overflow-x: auto;
        padding-bottom: 12px;
        min-height: 60vh;
    }
    .lpe-kb-col {
        flex: 0 0 280px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        display: flex; flex-direction: column;
        max-height: 75vh;
    }
    .lpe-kb-col-head {
        padding: 10px 12px;
        font-weight: 700; color: #fff;
        border-radius: 12px 12px 0 0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .lpe-kb-col-body { padding: 10px; overflow-y: auto; flex: 1; }

    .lpe-kb-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
        cursor: pointer;
        text-decoration: none; color: inherit;
        display: block;
    }
    .lpe-kb-card:hover { box-shadow: 0 4px 12px rgba(16, 185, 129,.12); transform: translateY(-1px); }
    .lpe-kb-card-name { font-weight: 600; color: #1c1a4a; font-size: 13px; margin-bottom: 4px; }
    .lpe-kb-card-contact { font-size: 11px; color: #6b7280; margin-bottom: 6px; }
    .lpe-kb-card-meta { display: flex; gap: 8px; flex-wrap: wrap; font-size: 11px; }
    .lpe-kb-card-meta span { padding: 1px 7px; border-radius: 999px; background: #f1f5f9; color: #475569; }
    .lpe-kb-card-meta .overdue { background: #fef2f2; color: #ef4444; font-weight: 600; }
    .lpe-kb-card-meta .has-notes { background: #eef0fb; color: var(--corp-brand); font-weight: 600; }

    .lpe-kb-empty { font-size: 11px; color: #9ca3af; text-align: center; padding: 14px; font-style: italic; }

    /* Drag-drop states */
    .lpe-kb-card { transition: box-shadow .15s, transform .08s, opacity .12s; }
    .lpe-kb-card.dragging { opacity: .45; }
    .lpe-kb-col-body.drop-target { background: #ecfdf5; outline: 2px dashed var(--corp-brand, #10b981); outline-offset: -4px; border-radius: 8px; }
    .lpe-kb-hint { font-size: 12px; color: #6b7280; margin: 0 0 12px; display: inline-flex; align-items: center; gap: 6px; }
    .lpe-kb-saving { position: fixed; right: 18px; bottom: 18px; background: #111827; color: #fff; font-size: 13px; padding: 9px 14px; border-radius: 8px; opacity: 0; transform: translateY(8px); transition: opacity .2s, transform .2s; z-index: 1080; }
    .lpe-kb-saving.show { opacity: 1; transform: translateY(0); }
    .lpe-kb-saving.err { background: #dc2626; }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .lpe-kb-col  { background:#17233a; border-color:#2a3a55; }
    html[data-theme="dark"] .lpe-kb-card { background:#1e293b; border-color:#2a3a55; box-shadow:none; }
    html[data-theme="dark"] .lpe-kb-card-name    { color:#e2e8f0; }
    html[data-theme="dark"] .lpe-kb-card-contact { color:#94a3b8; }
    html[data-theme="dark"] .lpe-kb-card-meta span:not(.overdue):not(.has-notes) { background:#22304a; color:#94a3b8; }
    html[data-theme="dark"] .lpe-kb-empty { color:#94a3b8; }
    html[data-theme="dark"] .lpe-kb-hint  { color:#94a3b8; }
</style>

<div class="dashboard__content-wrap">
    <div class="dashboard__content-title d-flex flex-wrap justify-content-between mb-2">
        <h4 class="title">{{ __('Lead pipeline') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('instructor.landing-page-enquiry.index') }}" class="btn btn-outline-primary">
                ☰ {{ __('Table view') }}
            </a>
            @if (userAuth()->role == 'instructor' || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student']) && checkPermissionView('landing-page-enquiry-create')))
                <a href="{{ route('instructor.landing-page-enquiry.create') }}" class="btn btn-primary">
                    + {{ __('Add New') }}
                </a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('instructor.landing-page-enquiry.kanban') }}" class="lpe-kb-toolbar">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <input type="text" name="q" value="{{ $filters['q'] }}"
                   class="form-control lpe-kb-search"
                   placeholder="{{ __('Search by name, email, phone…') }}">
            <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control" style="max-width:170px;" aria-label="{{ __('From date') }}">
            <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control" style="max-width:170px;" aria-label="{{ __('To date') }}">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Apply') }}</button>
            <a href="{{ route('instructor.landing-page-enquiry.kanban') }}"
               class="btn btn-outline-secondary" aria-label="{{ __('Clear filters') }}">×</a>
        </div>
    </form>

    <p class="lpe-kb-hint"><i class="fa fa-arrows-up-down-left-right"></i> {{ __('Drag a lead card between columns to change its stage — saved automatically.') }}</p>

    <div class="lpe-kb-board">
        @foreach ($statusOptions as $key => $opt)
            @php $data = $columns[$key] ?? ['rows' => collect(), 'total' => 0]; @endphp
            <div class="lpe-kb-col" data-status="{{ $key }}">
                <div class="lpe-kb-col-head" style="background-color: {{ $opt['color'] }};">
                    <span>
                        <i class="fa {{ $opt['icon'] }}" style="margin-right:6px;"></i>
                        {{ $opt['label'] }}
                    </span>
                    <span class="lpe-kb-count">{{ $data['rows']->count() }}@if ($data['total'] > $data['rows']->count())<small style="opacity:.7;"> / {{ $data['total'] }}</small>@endif</span>
                </div>
                <div class="lpe-kb-col-body" data-status="{{ $key }}">
                    @forelse ($data['rows'] as $r)
                        @php
                            $overdue = $r->follow_up_at && \Illuminate\Support\Carbon::parse($r->follow_up_at)->isPast();
                        @endphp
                        <a href="{{ route('instructor.landing-page-enquiry.show', $r->id) }}"
                           class="lpe-kb-card" draggable="true"
                           data-id="{{ $r->id }}" data-status="{{ $key }}">
                            <div class="lpe-kb-card-name">
                                {{ trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: __('Unnamed') }}
                            </div>
                            <div class="lpe-kb-card-contact">
                                @if ($r->email) ✉ {{ \Illuminate\Support\Str::limit($r->email, 30) }} <br> @endif
                                @if ($r->phone) ☎ {{ $r->phone }} @endif
                            </div>
                            <div class="lpe-kb-card-meta">
                                @if ($r->service)
                                    <span>{{ \Illuminate\Support\Str::limit($r->service, 18) }}</span>
                                @endif
                                @if ($r->notes_count > 0)
                                    <span class="has-notes">💬 {{ $r->notes_count }}</span>
                                @endif
                                @if ($overdue)
                                    <span class="overdue">⚠ {{ __('Overdue') }}</span>
                                @elseif ($r->follow_up_at)
                                    <span>⏰ {{ \Illuminate\Support\Carbon::parse($r->follow_up_at)->diffForHumans() }}</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="lpe-kb-empty">{{ __('No leads') }}</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="lpe-kb-saving" id="lpeKbToast"></div>

<script nonce="{{ csp_nonce() }}">
(function () {
    var BASE  = "{{ url('/instructor/landing-page-enquiry') }}";
    var TOKEN = "{{ csrf_token() }}";
    var toast = document.getElementById('lpeKbToast');
    var dragged = null;

    function flash(msg, isErr) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.toggle('err', !!isErr);
        toast.classList.add('show');
        setTimeout(function () { toast.classList.remove('show'); }, 1800);
    }

    function syncCount(col) {
        if (!col) return;
        var body = col.querySelector('.lpe-kb-col-body');
        var n = body.querySelectorAll('.lpe-kb-card').length;
        var cnt = col.querySelector('.lpe-kb-count');
        if (cnt) cnt.textContent = n;
        var empty = body.querySelector('.lpe-kb-empty');
        if (n > 0 && empty) empty.remove();
        if (n === 0 && !empty) {
            var e = document.createElement('div');
            e.className = 'lpe-kb-empty';
            e.textContent = "{{ __('No leads') }}";
            body.appendChild(e);
        }
    }

    document.querySelectorAll('.lpe-kb-card').forEach(function (card) {
        card.addEventListener('dragstart', function (e) {
            dragged = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', card.dataset.id); } catch (x) {}
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            dragged = null;
            document.querySelectorAll('.drop-target').forEach(function (t) { t.classList.remove('drop-target'); });
        });
        // a drag must not also trigger the link navigation
        card.addEventListener('click', function (e) {
            if (card.dataset.justDragged) { e.preventDefault(); delete card.dataset.justDragged; }
        });
    });

    document.querySelectorAll('.lpe-kb-col-body').forEach(function (body) {
        body.addEventListener('dragover', function (e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; body.classList.add('drop-target'); });
        body.addEventListener('dragleave', function (e) { if (e.target === body) body.classList.remove('drop-target'); });
        body.addEventListener('drop', function (e) {
            e.preventDefault();
            body.classList.remove('drop-target');
            if (!dragged) return;

            var newStatus = body.dataset.status;
            var oldStatus = dragged.dataset.status;
            if (newStatus === oldStatus) return;

            var sourceCol = dragged.closest('.lpe-kb-col');
            var targetCol = body.closest('.lpe-kb-col');
            body.appendChild(dragged);
            dragged.dataset.status = newStatus;
            dragged.dataset.justDragged = '1';
            var movedCard = dragged;
            syncCount(sourceCol);
            syncCount(targetCol);

            fetch(BASE + '/' + movedCard.dataset.id + '/status', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': TOKEN },
                body: new URLSearchParams({ _token: TOKEN, status: newStatus })
            }).then(function (r) {
                if (!r.ok) throw new Error('save failed');
                flash("{{ __('Stage updated') }}", false);
            }).catch(function () {
                flash("{{ __('Could not save — reloading') }}", true);
                setTimeout(function () { window.location.reload(); }, 900);
            });
        });
    });
})();
</script>
@endsection
