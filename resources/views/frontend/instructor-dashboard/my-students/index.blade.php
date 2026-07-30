@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Coach roster — rebuilt on the platform corp design system (2026-05).
        Uses the same primitives as course-batches and payout (corp-page,
        corp-header, corp-kpi, corp-form-card, corp-table, corp-pill,
        corp-actions, corp-empty, btn-corp-primary) so it inherits proven
        responsive behaviour and never overflows the col-lg-10 column.

        Functional contract preserved 1:1:
          * $mystudents paginator (User rows, role=student)
          * route('instructor.my-students.create')
          * route('instructor.my-students.edit', id)
          * route('instructor.my-students.destroy', id)  ← inline DELETE form
          * Permission gate: instructor role OR checkPermissionView('coach-students-create')
    --}}

    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <style>
        /* Brand-tint overrides only — corp-page does the heavy lifting */
        #myStudents {
            --corp-brand:      {{ $brand->primaryColor ?: '#10b981' }};
            --corp-brand-2:    {{ $brand->accentColor  ?: '#059669' }};
            --corp-brand-grad: linear-gradient(135deg, var(--corp-brand) 0%, var(--corp-brand-2) 100%);
        }

        /* Brand-coloured avatar (initials) — local to this page */
        #myStudents .ms-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--corp-brand-grad);
            color: #fff; font-size: 13px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
            text-transform: uppercase; flex-shrink: 0;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }
        #myStudents .ms-student {
            display: flex; align-items: center; gap: 10px;
            min-width: 0;
        }
        #myStudents .ms-student__body { min-width: 0; }
        #myStudents .ms-student__name {
            font-weight: 600; color: var(--corp-text); font-size: 13px;
            line-height: 1.3;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            max-width: 220px;
        }
        #myStudents .ms-student__email {
            font-size: 11.5px; color: var(--corp-muted);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            max-width: 220px;
        }
    </style>

    @php
        $canAdd = userAuth()->role == 'instructor'
            || (!in_array(strtolower(trim(userAuth()->role)), ['instructor','student'])
                && checkPermissionView('coach-students-create'));
        $totalStudents = $mystudents->total();
        $pageActive    = $mystudents->where('status','active')->count();
        $pageBanned    = $mystudents->where('status','!=','active')->count();
    @endphp

    <div class="corp-page" id="myStudents">

        {{-- Header --}}
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Students') }}</h4>
                <p>{{ __('Every learner across your courses and batches — add, edit, or remove from your roster.') }}</p>
            </div>
            <div class="corp-header__actions">
                @if ($canAdd)
                    <a href="{{ route('instructor.my-students.create') }}" class="btn-corp-primary">
                        <i class="fas fa-plus"></i> {{ __('Add student') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- KPI icon chip styles (scoped) --}}
        <style>
            #myStudents .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
            #myStudents .corp-kpi__tile .ms-kpi-icon {
                position: absolute; top: 18px; left: 18px;
                width: 38px; height: 38px; border-radius: 10px;
                display: inline-flex; align-items: center; justify-content: center;
                background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
                color: var(--accent, var(--corp-brand));
                border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
                font-size: 15px;
            }
        </style>

        <style>
            /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
            html[data-theme="dark"] #myStudents .corp-kpi__tile .ms-kpi-icon {
                background: color-mix(in srgb, var(--accent, var(--corp-brand)) 22%, #1e293b);
            }
        </style>

        {{-- KPI strip --}}
        <div class="corp-kpi">
            <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
                <span class="ms-kpi-icon"><i class="fas fa-users"></i></span>
                <div class="corp-kpi__label">{{ __('Total students') }}</div>
                <div class="corp-kpi__value">{{ number_format($totalStudents) }}</div>
                <div class="corp-kpi__sub">{{ __('all-time on roster') }}</div>
            </div>
            <div class="corp-kpi__tile" style="--accent:#10b981;">
                <span class="ms-kpi-icon"><i class="fas fa-user-check"></i></span>
                <div class="corp-kpi__label">{{ __('Active on page') }}</div>
                <div class="corp-kpi__value" style="color:#047857;">{{ number_format($pageActive) }}</div>
                <div class="corp-kpi__sub">{{ __('paying / enrolled') }}</div>
            </div>
            <div class="corp-kpi__tile" style="--accent:#ef4444;">
                <span class="ms-kpi-icon"><i class="fas fa-user-slash"></i></span>
                <div class="corp-kpi__label">{{ __('Banned on page') }}</div>
                <div class="corp-kpi__value" style="color:{{ $pageBanned > 0 ? '#b91c1c' : 'var(--corp-text)' }};">
                    {{ number_format($pageBanned) }}
                </div>
                <div class="corp-kpi__sub">
                    {{ $pageBanned > 0 ? __('blocked from access') : __('all clear') }}
                </div>
            </div>
        </div>

        {{-- List card --}}
        <div class="corp-form-card" style="margin-top:14px;">
            <div class="corp-form-card__head" style="flex-wrap:wrap;">
                <h6 class="corp-form-card__title" style="width:100%;">
                    <i class="fas fa-users"></i>
                    {{ __('Student roster') }}
                    <span style="margin-left:auto; font-weight:500; color:var(--corp-muted); text-transform:none; letter-spacing:0; font-size:11px;">
                        {{ $totalStudents }} {{ trans_choice('student|students', $totalStudents) }}
                    </span>
                </h6>

                {{-- 2026-07-10 (New Changes for UI #7) — Status filter + Search.
                     Server-side (GET) so large rosters paginate correctly and the
                     filter survives page links via ->withQueryString(). --}}
                <form method="GET" action="{{ route('instructor.my-students.index') }}"
                      class="ms-filters"
                      style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; width:100%; margin-top:12px;">
                    <div style="position:relative; flex:1 1 240px; min-width:190px;">
                        <i class="fas fa-search"
                           style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--corp-muted); font-size:12px;"></i>
                        <input type="text" name="q" value="{{ $search ?? '' }}"
                               class="form-control"
                               placeholder="{{ __('Search name, email or phone') }}"
                               style="padding-left:32px; height:38px;">
                    </div>
                    <select name="status" class="form-select" style="width:auto; min-width:150px; height:38px;">
                        <option value="all"      @selected(($status ?? 'all') === 'all')>{{ __('All statuses') }}</option>
                        <option value="active"   @selected(($status ?? '') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(($status ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                        <option value="blocked"  @selected(($status ?? '') === 'blocked')>{{ __('Blocked') }}</option>
                    </select>
                    <button type="submit" class="btn-corp-primary" style="height:38px;">
                        <i class="fas fa-filter"></i> {{ __('Apply') }}
                    </button>
                    @if (($search ?? '') !== '' || ($status ?? 'all') !== 'all')
                        <a href="{{ route('instructor.my-students.index') }}" class="btn-corp-secondary" style="height:38px;">
                            <i class="fas fa-times"></i> {{ __('Reset') }}
                        </a>
                    @endif
                </form>
            </div>

            @if ($mystudents->count() > 0)
                {{-- 2026-07-15 — bulk "Assign to batch" bar (shows when rows selected). --}}
                <div id="msBulkBar" style="display:none; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 18px; border-bottom:1px solid var(--corp-line-soft); background:#f5f7ff;">
                    <span id="msBulkCount" style="font-weight:600; color:var(--corp-brand);">0 {{ __('selected') }}</span>
                    <select id="msBulkBatch" class="form-control form-control-sm" style="max-width:300px;">
                        <option value="">{{ __('Choose a batch…') }}</option>
                        @foreach($batchOptions as $b)<option value="{{ $b->id }}">{{ optional($b->course)->title }} — {{ $b->title }}</option>@endforeach
                    </select>
                    <input id="msBulkReason" class="form-control form-control-sm" style="max-width:220px;" placeholder="{{ __('Reason (optional)') }}">
                    <button id="msBulkAssign" class="btn-corp-primary" style="padding:6px 14px;"><i class="fas fa-users"></i> {{ __('Assign to batch') }}</button>
                    <span id="msBulkMsg" style="font-size:12.5px;"></span>
                </div>
                <div class="corp-table-wrap" style="border:none; border-radius:0;">
                    <table class="corp-table">
                        <thead>
                            <tr>
                                <th style="width:34px;"><input type="checkbox" id="msSelectAll" aria-label="{{ __('Select all') }}"></th>
                                <th style="width:52px;">#</th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Joined at') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th style="width:160px; text-align:right;">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mystudents as $index => $s)
                                <tr>
                                    <td><input type="checkbox" class="ms-check" value="{{ $s->id }}" data-name="{{ $s->name }}" aria-label="{{ __('Select') }} {{ $s->name }}"></td>
                                    <td data-label="#">{{ $mystudents->firstItem() + $index }}</td>
                                    <td data-label="{{ __('Student') }}">
                                        <div class="ms-student">
                                            <div class="ms-avatar">{{ strtoupper(substr($s->name, 0, 2)) }}</div>
                                            <div class="ms-student__body">
                                                <div class="ms-student__name" title="{{ $s->name }}">{{ $s->name }}</div>
                                                <div class="ms-student__email" title="{{ $s->email }}">{{ $s->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="{{ __('Joined at') }}">
                                        <span style="font-size:12px; color:var(--corp-text-2); white-space:nowrap;">
                                            <i class="far fa-clock" style="color:var(--corp-brand); font-size:11px;"></i>
                                            {{ $s->created_at->format('h:iA, d M Y') }}
                                        </span>
                                    </td>
                                    <td data-label="{{ __('Status') }}">
                                        @if ($s->is_banned === 'yes')
                                            <span class="corp-pill corp-pill--danger">{{ __('Blocked') }}</span>
                                        @elseif ($s->status === 'active')
                                            <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                                        @else
                                            <span class="corp-pill corp-pill--muted">{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('Actions') }}" style="text-align:right;">
                                        <div class="corp-actions" style="justify-content:flex-end;">
                                            <button type="button" class="corp-actions__btn ms-batch-btn"
                                                    data-student-id="{{ $s->id }}" data-student-name="{{ $s->name }}"
                                                    title="{{ __('Manage batch') }}">
                                                <i class="fas fa-users-cog"></i>
                                            </button>
                                            <a href="{{ route('instructor.my-students.edit', [$s->id]) }}"
                                               class="corp-actions__btn"
                                               title="{{ __('Edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('instructor.my-students.destroy', [$s->id]) }}"
                                                  style="display:inline;"
                                                  onsubmit="return confirm('{{ addslashes(__('Delete :name? This cannot be undone.', ['name' => $s->name])) }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="corp-actions__btn corp-actions__btn--danger"
                                                        title="{{ __('Delete') }}">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($mystudents->hasPages())
                    <div style="padding:14px 18px; border-top:1px solid var(--corp-line-soft); display:flex; justify-content:center;">
                        {{ $mystudents->links() }}
                    </div>
                @endif
            @else
                <div class="corp-form-card__body">
                    <div class="corp-empty">
                        @if (($search ?? '') !== '' || ($status ?? 'all') !== 'all')
                            {{-- Filtered, but nothing matched. --}}
                            <div class="corp-empty__icon"><i class="fas fa-search"></i></div>
                            <div class="corp-empty__title">{{ __('No matching students') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('No students match your search or status filter. Try adjusting them.') }}
                            </div>
                            <a href="{{ route('instructor.my-students.index') }}" class="btn-corp-secondary" style="margin-top:14px;">
                                <i class="fas fa-times"></i> {{ __('Clear filters') }}
                            </a>
                        @else
                            <div class="corp-empty__icon"><i class="fas fa-user-graduate"></i></div>
                            <div class="corp-empty__title">{{ __('No students yet') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('Add your first student or wait for someone to enroll in one of your courses.') }}
                            </div>
                            @if ($canAdd)
                                <a href="{{ route('instructor.my-students.create') }}" class="btn-corp-primary" style="margin-top:14px;">
                                    <i class="fas fa-plus"></i> {{ __('Add your first student') }}
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </div>

    @include('frontend.instructor-dashboard.my-students._batch-modal')

    <script nonce="{{ csp_nonce() }}">
    (function(){
        var CSRF = (document.querySelector('meta[name="csrf-token"]')||{}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
        function post(url, obj){
            var fd = new FormData(); fd.set('_token', CSRF);
            Object.keys(obj).forEach(function(k){ if(Array.isArray(obj[k])){ obj[k].forEach(function(v){ fd.append(k+'[]', v); }); } else fd.set(k, obj[k]); });
            return fetch(url, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, credentials:'same-origin'}).then(function(r){ return r.json().catch(function(){ return {}; }); });
        }
        var bar = document.getElementById('msBulkBar'), cnt = document.getElementById('msBulkCount'), bmsg = document.getElementById('msBulkMsg');
        function selected(){ return Array.prototype.slice.call(document.querySelectorAll('.ms-check:checked')).map(function(c){ return c.value; }); }
        function refreshBar(){ var n = selected().length; if(n){ bar.style.display='flex'; cnt.textContent = n + ' {{ __('selected') }}'; } else { bar.style.display='none'; } }
        document.addEventListener('change', function(e){
            if(e.target.id==='msSelectAll'){ document.querySelectorAll('.ms-check').forEach(function(c){ c.checked = e.target.checked; }); refreshBar(); }
            else if(e.target.classList && e.target.classList.contains('ms-check')){ refreshBar(); }
        });
        var bulkBtn = document.getElementById('msBulkAssign');
        if(bulkBtn) bulkBtn.addEventListener('click', function(){
            var ids = selected(), batchId = document.getElementById('msBulkBatch').value, reason = document.getElementById('msBulkReason').value;
            if(!ids.length || !batchId){ bmsg.textContent = "{{ __('Pick students and a batch.') }}"; bmsg.style.color='#b91c1c'; return; }
            bulkBtn.disabled = true; bmsg.style.color='#64748b'; bmsg.textContent = "{{ __('Assigning…') }}";
            post("{{ route('instructor.my-students.batch.bulk-assign') }}", {student_ids:ids, batch_id:batchId, reason:reason}).then(function(d){
                if(d && d.ok){ var fail = (d.failed||[]).length; bmsg.style.color = fail? '#b45309':'#047857';
                    bmsg.textContent = d.assigned + " {{ __('assigned') }}" + (fail? (' · ' + fail + " {{ __('failed') }}") : ''); setTimeout(function(){ location.reload(); }, 1400); }
                else { bmsg.style.color='#b91c1c'; bmsg.textContent = (d && d.message) || "{{ __('Failed.') }}"; }
            }).finally(function(){ bulkBtn.disabled = false; });
        });
    })();
    </script>
@endsection
