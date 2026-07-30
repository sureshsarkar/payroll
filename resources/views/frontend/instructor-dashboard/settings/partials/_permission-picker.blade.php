{{--
    Permission Picker — Resource × Action MATRIX layout.
    2026-05-20 enterprise upgrade #2 — replaces the inline-checkbox wall
    with a real IAM-style matrix: rows = resource (Courses, Coach Sells,
    Course Batches…), columns = action (Access, Show, Create, Edit, Delete).
    Same pattern as Microsoft Entra, AWS IAM, Salesforce, Atlassian.

    Wire format unchanged:
      <input type="checkbox" name="{{ $pickerField }}" value="{{ $perm->id }}">
    Controllers receive an array of permission ids exactly as before.

    Required:
        $pickerPermissions  Collection<CoachStaffPermission>  available perms
        $pickerField        string   HTML name attribute (e.g. 'permissions[]')
        $pickerChecked      array<int>  ids pre-checked on load
    Optional:
        $pickerId           string   unique DOM id (default 'perm-picker-1')
        $pickerEmptyText    string   message when zero permissions available

    Slug → matrix decomposition:
      Suffixes [-show, -create, -edit, -delete] split into a (resource,
      action) pair. Bare slug = Access. Any resource that only has a
      bare slug AND no sibling action permissions is moved to the
      "Other capabilities" section below the matrix.
--}}
@php
    $pickerId        = $pickerId        ?? 'perm-picker-1';
    $pickerEmptyText = $pickerEmptyText ?? __('No permissions available.');
    $pickerChecked   = collect($pickerChecked ?? [])->map(fn ($v) => (int) $v)->all();

    // Suffixes the matrix understands, in column order.
    $matrixActions = ['access', 'show', 'create', 'edit', 'delete'];
    $actionLabels  = [
        'access' => __('Access'),
        'show'   => __('Show'),
        'create' => __('Create'),
        'edit'   => __('Edit'),
        'delete' => __('Delete'),
    ];

    // Build resourceMap[resource][action] = $perm
    $resourceMap = [];
    foreach (collect($pickerPermissions ?? []) as $perm) {
        $slug = (string) ($perm->slug ?? '');
        $matched = false;
        foreach (['show', 'create', 'edit', 'delete'] as $action) {
            if (str_ends_with($slug, '-' . $action)) {
                $resource = substr($slug, 0, -strlen($action) - 1);
                $resourceMap[$resource][$action] = $perm;
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            // Bare slug — Access for its own resource. May be standalone.
            $resourceMap[$slug]['access'] = $perm;
        }
    }
    ksort($resourceMap);

    // Split: matrix rows (multi-action) vs standalone (single bare slug only).
    $matrixRows = [];
    $standalone = [];
    foreach ($resourceMap as $resource => $actions) {
        if (count($actions) === 1 && isset($actions['access'])) {
            $standalone[$resource] = $actions['access'];
        } else {
            $matrixRows[$resource] = $actions;
        }
    }

    // 2026-07-04 — group the ~40 matrix rows by module so the picker reads as
    // scannable sections instead of one long wall. Purely presentational; the
    // wire format (checkbox name/value) is unchanged.
    $pickerGroupOrder = ['Content', 'People & Access', 'Commerce', 'Settings', 'Other'];
    $categoryOf = function (string $r) {
        if ($r === 'settings' || str_starts_with($r, 'settings-')) return 'Settings';
        $people   = ['coach-students', 'coach-staff', 'roles', 'permissions', 'teacher-batches', 'landing-page-enquiry', 'pricing-enquiries', 'trial-sessions', 'instant-meetings'];
        $commerce = ['coach-orders', 'coach-sells', 'coach-coupons', 'fees', 'payout', 'tax', 'membership', 'referral', 'subscription-histories', 'payment-gateways'];
        $content  = ['courses', 'course-batches', 'live-classes', 'announcements', 'certificate', 'blogs', 'menus', 'pages', 'course-bundle', 'website-builder', 'gallery'];
        if (in_array($r, $people, true))   return 'People & Access';
        if (in_array($r, $commerce, true)) return 'Commerce';
        if (in_array($r, $content, true))  return 'Content';
        return 'Other';
    };
    $groupedRows = [];
    foreach ($matrixRows as $resource => $actions) {
        $groupedRows[$categoryOf($resource)][$resource] = $actions;
    }
    $orderedGroups = [];
    foreach ($pickerGroupOrder as $g) {
        if (!empty($groupedRows[$g])) $orderedGroups[$g] = $groupedRows[$g];
    }
    foreach ($groupedRows as $g => $rows) {          // any unforeseen group → last
        if (!isset($orderedGroups[$g])) $orderedGroups[$g] = $rows;
    }

    // Pre-compute "is this id checked?" lookups for the blade output.
    $isChecked = fn ($id) => in_array((int) $id, $pickerChecked, true);

    // Humanise a slug for display.
    $humanise = function (string $slug) {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    };

    // Optional ROLE DEFAULTS — when provided (staff forms), the picker shows a
    // reset control + badges each cell that differs from the role (grant/revoke).
    $pickerRoleDefaults = isset($pickerRoleDefaults)
        ? collect($pickerRoleDefaults)->map(fn ($v) => (int) $v)->values()->all()
        : null;
@endphp

<div id="{{ $pickerId }}"
     data-original-checked="{{ json_encode(array_values($pickerChecked)) }}"
     @if(is_array($pickerRoleDefaults)) data-role-defaults="{{ json_encode($pickerRoleDefaults) }}" @endif>

    {{-- Toolbar ────────────────────────────────────────────────── --}}
    <div class="pp-toolbar">
        <input type="search" class="pp-search" placeholder="{{ __('Search by resource or slug…  (press /)') }}" autocomplete="off">
        <span class="pp-count">
            <span class="pp-checked">0</span> / <span class="pp-total">0</span> {{ __('selected') }}
        </span>
        <button type="button" class="pp-btn pp-only-granted" aria-pressed="false"
                title="{{ __('Show only resources with at least one permission granted') }}">
            <i class="fas fa-filter" style="font-size:10px;"></i> {{ __('Only granted') }}
        </button>
        <button type="button" class="pp-btn pp-select-all">{{ __('Select all') }}</button>
        <button type="button" class="pp-btn pp-clear-all">{{ __('Clear all') }}</button>
        @if(is_array($pickerRoleDefaults))
            <button type="button" class="pp-btn pp-reset-role" title="{{ __('Restore this role’s default permissions') }}">
                <i class="fas fa-undo" style="font-size:10px;"></i> {{ __('Reset to role') }}
            </button>
            <span class="pp-override-count" hidden>
                <span class="pp-ov-n">0</span> {{ __('override(s)') }}
            </span>
        @endif
    </div>

    {{-- Effective chip strip + live diff ──────────────────────── --}}
    <div class="corp-effective pp-effective">
        <span class="corp-effective__label">{{ __('Effective:') }}</span>
        <span class="pp-effective-chips">
            <span class="corp-effective__chip">
                <i class="fas fa-inbox" style="font-size:10px;"></i>
                <span class="pp-modules-covered">0</span> {{ __('resources') }}
            </span>
            <span class="corp-effective__chip corp-effective__chip--brand">
                <i class="fas fa-key" style="font-size:10px;"></i>
                <span class="pp-perms-covered">0</span> {{ __('permissions') }}
            </span>
        </span>
        <span class="corp-effective__diff pp-diff" style="display:none;">
            <span class="corp-diff-pill corp-diff-pill--add">
                +<span class="pp-diff-add">0</span> {{ __('to add') }}
            </span>
            <span class="corp-diff-pill corp-diff-pill--remove">
                −<span class="pp-diff-remove">0</span> {{ __('to remove') }}
            </span>
        </span>
    </div>

    {{-- MATRIX ─────────────────────────────────────────────────── --}}
    @if (empty($matrixRows) && empty($standalone))
        <div class="pp-empty">
            <div class="pp-empty__icon"><i class="fas fa-key"></i></div>
            <div>{{ $pickerEmptyText }}</div>
        </div>
    @else
        @if (!empty($matrixRows))
            <div class="pp-matrix-wrap">
                <table class="pp-matrix">
                    <thead>
                        <tr>
                            <th class="pp-matrix__resource-col">{{ __('Resource') }}</th>
                            @foreach ($matrixActions as $action)
                                <th class="pp-matrix__action-col" data-action="{{ $action }}">
                                    <button type="button" class="pp-matrix__col-toggle"
                                            title="{{ __('Toggle :a for every row', ['a' => $actionLabels[$action]]) }}">
                                        {{ $actionLabels[$action] }}
                                    </button>
                                </th>
                            @endforeach
                            <th class="pp-matrix__count-col">{{ __('Granted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orderedGroups as $groupName => $rows)
                            <tr class="pp-group-row" data-group="{{ $groupName }}">
                                <td class="pp-group-cell" colspan="{{ count($matrixActions) + 2 }}">
                                    <span class="pp-group-name">{{ __($groupName) }}</span>
                                    <span class="pp-group-meta">{{ count($rows) }}</span>
                                </td>
                            </tr>
                            @foreach ($rows as $resource => $actions)
                            <tr data-resource="{{ $resource }}" data-group="{{ $groupName }}">
                                <td class="pp-matrix__resource">
                                    <button type="button" class="pp-matrix__row-toggle"
                                            title="{{ __('Toggle every capability for :r', ['r' => $humanise($resource)]) }}">
                                        <span class="pp-matrix__row-name">{{ $humanise($resource) }}</span>
                                        <code class="pp-matrix__row-slug">{{ $resource }}</code>
                                    </button>
                                </td>
                                @foreach ($matrixActions as $action)
                                    @if (isset($actions[$action]))
                                        @php $perm = $actions[$action]; @endphp
                                        <td class="pp-matrix__cell">
                                            <label class="pp-matrix__cb">
                                                <input type="checkbox" name="{{ $pickerField }}"
                                                       value="{{ $perm->id }}"
                                                       data-action="{{ $action }}"
                                                       @checked($isChecked($perm->id))>
                                                <span class="pp-matrix__cb-box"></span>
                                            </label>
                                        </td>
                                    @else
                                        <td class="pp-matrix__cell pp-matrix__cell--na" title="{{ __('Not available for this resource') }}">
                                            <span>—</span>
                                        </td>
                                    @endif
                                @endforeach
                                <td class="pp-matrix__count">
                                    <span class="pp-matrix__count-pill">
                                        <span class="pp-row-checked">0</span> / {{ count($actions) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Standalone capabilities (single-permission items) ──── --}}
        @if (!empty($standalone))
            <div class="pp-standalone">
                <div class="pp-standalone__head">
                    <i class="fas fa-puzzle-piece" style="color:var(--corp-brand);"></i>
                    <span>{{ __('Other Capabilities') }}</span>
                    <span class="pp-standalone__hint">{{ __('Single-permission features that don\'t fit the access matrix.') }}</span>
                </div>
                <div class="pp-standalone__grid">
                    @foreach ($standalone as $resource => $perm)
                        <label class="pp-standalone__item">
                            <input type="checkbox" name="{{ $pickerField }}" value="{{ $perm->id }}"
                                   @checked($isChecked($perm->id))>
                            <span>
                                <span class="pp-standalone__name">{{ $perm->name ?: $humanise($resource) }}</span>
                                <code class="pp-standalone__slug">{{ $resource }}</code>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>

<style>
/* ════════════════════════════════════════════════════════════════
   Picker shell — toolbar + counts (unchanged from prior version)
   ════════════════════════════════════════════════════════════════ */
#{{ $pickerId }} .pp-toolbar {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    padding: 12px 18px;
    border-bottom: 1px solid var(--corp-line-soft);
    background: #fcfcfd;
}
#{{ $pickerId }} .pp-toolbar input[type="search"] {
    flex: 1; min-width: 240px; height: 36px;
    border: 1px solid var(--corp-line); border-radius: 8px;
    padding: 6px 12px; font-size: 13px;
}
#{{ $pickerId }} .pp-count {
    font-size: 11px; color: var(--corp-muted); font-weight: 600;
    padding: 4px 10px; background: #fff;
    border: 1px solid var(--corp-line); border-radius: 6px;
}
#{{ $pickerId }} .pp-btn {
    background: transparent; border: 1px solid var(--corp-line);
    color: var(--corp-text); padding: 6px 12px;
    font-size: 12px; border-radius: 6px; cursor: pointer;
}
#{{ $pickerId }} .pp-btn:hover { background: #f3f4f6; }

#{{ $pickerId }} .pp-empty {
    padding: 48px 24px; text-align: center; color: var(--corp-muted);
}
#{{ $pickerId }} .pp-empty__icon {
    font-size: 28px; color: #d1d5db; margin-bottom: 8px;
}

/* ════════════════════════════════════════════════════════════════
   MATRIX TABLE — the actual upgrade
   ════════════════════════════════════════════════════════════════ */
#{{ $pickerId }} .pp-matrix-wrap {
    padding: 14px 18px 6px;
    overflow: auto;
    max-height: 620px;            /* long catalogs scroll inside; header stays put */
}
/* Active state for the "Only granted" filter toggle. */
#{{ $pickerId }} .pp-only-granted.is-on {
    background: var(--corp-brand-bg);
    border-color: var(--corp-brand-border);
    color: var(--corp-brand-deep);
}
/* Module section header row inside the matrix. */
#{{ $pickerId }} .pp-group-row .pp-group-cell {
    padding: 8px 16px;
    background: var(--corp-brand-bg);
    border-bottom: 1px solid var(--corp-line);
    position: sticky;
    top: 40px;                    /* sits just under the sticky column header */
    z-index: 2;
}
#{{ $pickerId }} .pp-group-name {
    font-size: 10.5px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.7px; color: var(--corp-brand-deep);
}
#{{ $pickerId }} .pp-group-meta {
    display: inline-block; margin-left: 8px;
    min-width: 18px; padding: 1px 7px;
    font-size: 10px; font-weight: 700; text-align: center;
    color: var(--corp-muted); background: #fff;
    border: 1px solid var(--corp-line); border-radius: 999px;
}
#{{ $pickerId }} .pp-matrix {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    border: 1px solid var(--corp-line);
    border-radius: 10px;
    overflow: hidden;
    font-size: 13px;
}
/* Header — sticky so column labels stay visible while scrolling 40 rows. */
#{{ $pickerId }} .pp-matrix thead { background: #fafbfc; }
#{{ $pickerId }} .pp-matrix th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #fafbfc;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--corp-muted);
    padding: 0;
    border-bottom: 1px solid var(--corp-line);
    white-space: nowrap;
}
#{{ $pickerId }} .pp-matrix th.pp-matrix__resource-col {
    text-align: left;
    padding: 12px 16px;
    width: 30%;
}
#{{ $pickerId }} .pp-matrix th.pp-matrix__count-col {
    text-align: center;
    padding: 12px 12px;
    width: 90px;
    color: var(--corp-muted);
}
#{{ $pickerId }} .pp-matrix__col-toggle {
    width: 100%; min-width: 80px;
    padding: 12px 8px;
    background: transparent; border: none;
    font: inherit; color: inherit;
    text-transform: uppercase; letter-spacing: 0.4px; font-size: 11px; font-weight: 700;
    cursor: pointer;
    transition: color .12s, background .12s;
}
#{{ $pickerId }} .pp-matrix__col-toggle:hover {
    color: var(--corp-brand); background: var(--corp-brand-bg);
}

/* Body */
#{{ $pickerId }} .pp-matrix tbody tr {
    transition: background .12s;
}
#{{ $pickerId }} .pp-matrix tbody tr:nth-child(even) { background: #fcfcfd; }
#{{ $pickerId }} .pp-matrix tbody tr:hover { background: var(--corp-brand-bg); }
#{{ $pickerId }} .pp-matrix tbody tr.is-allgranted .pp-matrix__count-pill { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
#{{ $pickerId }} .pp-matrix tbody tr.is-none .pp-matrix__count-pill { background: #f3f4f6; color: var(--corp-muted); border-color: var(--corp-line); }

#{{ $pickerId }} .pp-matrix td {
    padding: 0;
    border-bottom: 1px solid var(--corp-line-soft);
    text-align: center;
    vertical-align: middle;
}
#{{ $pickerId }} .pp-matrix tbody tr:last-child td { border-bottom: none; }

/* Resource column (left) */
#{{ $pickerId }} .pp-matrix__resource {
    text-align: left;
    padding: 0;
    border-right: 1px solid var(--corp-line-soft);
}
#{{ $pickerId }} .pp-matrix__row-toggle {
    width: 100%;
    padding: 12px 16px;
    text-align: left;
    background: transparent; border: none;
    cursor: pointer;
    transition: background .12s;
    display: block;
}
#{{ $pickerId }} .pp-matrix__row-toggle:hover { background: var(--corp-brand-bg); }
#{{ $pickerId }} .pp-matrix__row-name {
    display: block;
    font-weight: 600;
    color: var(--corp-text);
    font-size: 13px;
    line-height: 1.3;
}
#{{ $pickerId }} .pp-matrix__row-slug {
    display: block;
    font-size: 10px;
    color: var(--corp-muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    margin-top: 2px;
    background: transparent;
    padding: 0;
}

/* Cell + checkbox */
#{{ $pickerId }} .pp-matrix__cell {
    padding: 0;
    position: relative;
}
#{{ $pickerId }} .pp-matrix__cell--na {
    color: #d7dce3;
    font-size: 13px;
    font-weight: 400;
    background: #fbfcfd;        /* calm solid — the — glyph alone reads as "N/A" */
}
#{{ $pickerId }} .pp-matrix__cb {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 14px 0;
    cursor: pointer;
    margin: 0;
}
#{{ $pickerId }} .pp-matrix__cb input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
#{{ $pickerId }} .pp-matrix__cb-box {
    display: inline-block;
    width: 18px; height: 18px;
    border: 1.5px solid var(--corp-line);
    border-radius: 4px;
    background: #fff;
    position: relative;
    transition: all .12s;
}
#{{ $pickerId }} .pp-matrix__cb:hover .pp-matrix__cb-box {
    border-color: var(--corp-brand);
    box-shadow: 0 0 0 3px rgba(var(--corp-brand-rgb),.12);
}
#{{ $pickerId }} .pp-matrix__cb input:checked + .pp-matrix__cb-box {
    background: var(--corp-brand);
    border-color: var(--corp-brand);
}
#{{ $pickerId }} .pp-matrix__cb input:checked + .pp-matrix__cb-box::after {
    content: '';
    position: absolute;
    left: 5px; top: 1px;
    width: 6px; height: 11px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Count pill */
#{{ $pickerId }} .pp-matrix__count {
    padding: 12px 8px;
    border-left: 1px solid var(--corp-line-soft);
}
#{{ $pickerId }} .pp-matrix__count-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    background: var(--corp-brand-bg);
    color: var(--corp-brand);
    font-size: 11px;
    font-weight: 700;
    border: 1px solid var(--corp-brand-border);
    min-width: 50px;
}

/* ════════════════════════════════════════════════════════════════
   STANDALONE — single-permission capabilities (Setting,
   Landing Page Builder, etc.)
   ════════════════════════════════════════════════════════════════ */
#{{ $pickerId }} .pp-standalone {
    margin: 6px 18px 14px;
    background: #fff;
    border: 1px solid var(--corp-line);
    border-radius: 10px;
    overflow: hidden;
}
#{{ $pickerId }} .pp-standalone__head {
    padding: 10px 16px;
    background: #fafbfc;
    border-bottom: 1px solid var(--corp-line-soft);
    display: flex; align-items: center; gap: 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--corp-text);
}
#{{ $pickerId }} .pp-standalone__hint {
    text-transform: none;
    font-weight: 400;
    color: var(--corp-muted);
    font-size: 11px;
    margin-left: auto;
}
#{{ $pickerId }} .pp-standalone__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 0;
}
#{{ $pickerId }} .pp-standalone__item {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px;
    cursor: pointer;
    border-right: 1px solid var(--corp-line-soft);
    border-bottom: 1px solid var(--corp-line-soft);
    transition: background .12s;
}
#{{ $pickerId }} .pp-standalone__item:hover { background: var(--corp-brand-bg); }
#{{ $pickerId }} .pp-standalone__item input[type="checkbox"] {
    accent-color: var(--corp-brand);
    width: 16px; height: 16px;
    margin-top: 2px;
    cursor: pointer;
    flex-shrink: 0;
}
#{{ $pickerId }} .pp-standalone__name {
    display: block;
    font-size: 13px; font-weight: 600;
    color: var(--corp-text);
    line-height: 1.3;
}
#{{ $pickerId }} .pp-standalone__slug {
    display: block;
    font-size: 10px; color: var(--corp-muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    margin-top: 2px;
    background: transparent;
    padding: 0;
}

@media (max-width: 768px) {
    #{{ $pickerId }} .pp-matrix__action-col,
    #{{ $pickerId }} .pp-matrix__cell { min-width: 64px; }
    #{{ $pickerId }} .pp-matrix__row-name { font-size: 12px; }
    #{{ $pickerId }} .pp-matrix__row-slug { display: none; }
}
</style>

<script>
(function () {
    var $root = document.getElementById({!! json_encode($pickerId) !!});
    if (!$root) return;

    // Original-checked set (for diff vs current).
    var original;
    try { original = JSON.parse($root.dataset.originalChecked || '[]').map(Number); }
    catch (e) { original = []; }
    var originalSet = new Set(original);

    var search       = $root.querySelector('.pp-search');
    var countCh      = $root.querySelector('.pp-checked');
    var countTot     = $root.querySelector('.pp-total');
    var modulesCount = $root.querySelector('.pp-modules-covered');
    var permsCount   = $root.querySelector('.pp-perms-covered');
    var diffWrap     = $root.querySelector('.pp-diff');
    var diffAdd      = $root.querySelector('.pp-diff-add');
    var diffRemove   = $root.querySelector('.pp-diff-remove');

    function allCheckboxes() {
        return $root.querySelectorAll('input[type=checkbox][name]');
    }

    function updateRowCounts() {
        $root.querySelectorAll('.pp-matrix tbody tr[data-resource]').forEach(function (tr) {
            var boxes = tr.querySelectorAll('input[type=checkbox][name]');
            var ck    = tr.querySelectorAll('input[type=checkbox][name]:checked');
            var pill  = tr.querySelector('.pp-row-checked');
            if (pill) pill.textContent = ck.length;
            tr.classList.toggle('is-allgranted', boxes.length > 0 && ck.length === boxes.length);
            tr.classList.toggle('is-none', ck.length === 0);
        });
    }

    function updateCounts() {
        var boxes   = allCheckboxes();
        var checked = $root.querySelectorAll('input[type=checkbox][name]:checked');
        countTot.textContent = boxes.length;
        countCh.textContent  = checked.length;

        // Effective: distinct resources covered (matrix rows + standalone items checked)
        var resourcesCovered = 0;
        $root.querySelectorAll('.pp-matrix tbody tr[data-resource]').forEach(function (tr) {
            if (tr.querySelector('input[type=checkbox][name]:checked')) resourcesCovered++;
        });
        $root.querySelectorAll('.pp-standalone__item input[type=checkbox][name]:checked').forEach(function () {
            resourcesCovered++;
        });
        if (modulesCount) modulesCount.textContent = resourcesCovered;
        if (permsCount)   permsCount.textContent   = checked.length;

        // Diff vs original — only meaningful when original is non-empty.
        if (diffWrap) {
            var currentIds = new Set(
                Array.prototype.map.call(checked, function (b) { return Number(b.value); })
            );
            var added = 0, removed = 0;
            currentIds.forEach(function (id) { if (!originalSet.has(id)) added++; });
            originalSet.forEach(function (id) { if (!currentIds.has(id)) removed++; });
            if (originalSet.size > 0 && (added > 0 || removed > 0)) {
                diffWrap.style.display = '';
                diffAdd.textContent    = added;
                diffRemove.textContent = removed;
            } else {
                diffWrap.style.display = 'none';
            }
        }

        updateRowCounts();

        // Notify outside listeners (sticky bar) — keep the event name for back-compat.
        $root.dispatchEvent(new CustomEvent('pp:change', {
            detail: {
                total:          boxes.length,
                checked:        checked.length,
                modulesCovered: resourcesCovered,
            },
            bubbles: true,
        }));
    }
    updateCounts();

    // ── Row toggle (click resource name) ────────────────────────────
    $root.querySelectorAll('.pp-matrix__row-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var tr    = btn.closest('tr');
            var boxes = tr.querySelectorAll('input[type=checkbox][name]');
            var allOn = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !allOn; });
            updateCounts();
        });
    });

    // ── Column toggle (click header cell) ───────────────────────────
    $root.querySelectorAll('.pp-matrix__col-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var action = btn.closest('th').dataset.action;
            if (!action) return;
            var boxes = $root.querySelectorAll(
                '.pp-matrix tbody input[type=checkbox][name][data-action="' + action + '"]'
            );
            var allOn = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !allOn; });
            updateCounts();
        });
    });

    // ── Cell click — toggle the checkbox without ticking the label ──
    // The label wraps the box, so clicking already toggles it. Just ensure
    // we recount when a user clicks the visible cb-box pseudo-checkbox.
    $root.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[type=checkbox][name]')) {
            updateCounts();
        }
    });

    // ── Combined filter: search text AND the "Only granted" toggle ──
    // Both filters share one pass so they compose instead of fighting over
    // each row's display. Group headers hide when all their rows are hidden.
    var onlyGrantedBtn = $root.querySelector('.pp-only-granted');
    function onlyGrantedOn() {
        return !!onlyGrantedBtn && onlyGrantedBtn.getAttribute('aria-pressed') === 'true';
    }
    function refreshGroupHeaders() {
        $root.querySelectorAll('.pp-group-row').forEach(function (g) {
            var name = g.getAttribute('data-group') || '';
            var rows = $root.querySelectorAll(
                '.pp-matrix tbody tr[data-resource][data-group="' + (window.CSS && CSS.escape ? CSS.escape(name) : name) + '"]'
            );
            var anyVisible = Array.prototype.some.call(rows, function (r) { return r.style.display !== 'none'; });
            g.style.display = anyVisible ? '' : 'none';
        });
    }
    function applyFilters() {
        var q = (search.value || '').toLowerCase().trim();
        var granted = onlyGrantedOn();
        $root.querySelectorAll('.pp-matrix tbody tr[data-resource]').forEach(function (tr) {
            var show = true;
            if (q) {
                var text = (tr.dataset.resource || '') + ' ' +
                           (tr.querySelector('.pp-matrix__row-name')?.textContent || '');
                show = text.toLowerCase().indexOf(q) !== -1;
            }
            if (show && granted) {
                show = !!tr.querySelector('input[type=checkbox][name]:checked');
            }
            tr.style.display = show ? '' : 'none';
        });
        // Standalone items follow the same rules.
        $root.querySelectorAll('.pp-standalone__item').forEach(function (it) {
            var show = true;
            if (q) show = it.textContent.toLowerCase().indexOf(q) !== -1;
            if (show && granted) {
                var b = it.querySelector('input[type=checkbox][name]');
                show = !!(b && b.checked);
            }
            it.style.display = show ? '' : 'none';
        });
        refreshGroupHeaders();
    }
    search.addEventListener('input', applyFilters);
    if (onlyGrantedBtn) {
        onlyGrantedBtn.addEventListener('click', function () {
            var next = !onlyGrantedOn();
            onlyGrantedBtn.setAttribute('aria-pressed', next ? 'true' : 'false');
            onlyGrantedBtn.classList.toggle('is-on', next);
            applyFilters();
        });
    }

    // ── Bulk buttons ────────────────────────────────────────────────
    $root.querySelector('.pp-select-all').addEventListener('click', function () {
        // Only toggle visible rows / items
        $root.querySelectorAll('.pp-matrix tbody tr[data-resource]').forEach(function (tr) {
            if (tr.style.display === 'none') return;
            tr.querySelectorAll('input[type=checkbox][name]').forEach(function (b) { b.checked = true; });
        });
        $root.querySelectorAll('.pp-standalone__item').forEach(function (it) {
            if (it.style.display === 'none') return;
            var b = it.querySelector('input[type=checkbox][name]');
            if (b) b.checked = true;
        });
        updateCounts();
    });
    $root.querySelector('.pp-clear-all').addEventListener('click', function () {
        allCheckboxes().forEach(function (b) { b.checked = false; });
        updateCounts();
        applyFilters();   // keep the "Only granted" view in sync
    });

    // ── `/` jumps to search ────────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '')) {
            e.preventDefault();
            search.focus(); search.select();
        }
    });
})();
</script>

{{-- Staff OVERRIDE overlay — only active when role defaults were passed in.
     Badges each cell that differs from the role and wires the reset button.
     Fully self-contained; never runs for role forms (no data-role-defaults). --}}
<style>
#{{ $pickerId }} .pp-reset-role { border-color:var(--corp-brand-border); color:var(--corp-brand-deep); background:var(--corp-brand-bg); }
#{{ $pickerId }} .pp-reset-role:hover { background:#e0e7ff; }
#{{ $pickerId }} .pp-override-count { font-size:11px; font-weight:700; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:4px 10px; }
#{{ $pickerId }} .pp-ov-grant, #{{ $pickerId }} .pp-ov-revoke { position:relative; }
#{{ $pickerId }} .pp-ov-grant::after,
#{{ $pickerId }} .pp-ov-revoke::after {
    content:''; position:absolute; top:2px; right:2px; width:8px; height:8px; border-radius:50%;
    box-shadow:0 0 0 2px #fff;
}
#{{ $pickerId }} .pp-ov-grant::after  { background:#16a34a; }  /* granted beyond role */
#{{ $pickerId }} .pp-ov-revoke::after { background:#dc2626; }  /* removed from role   */
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] #{{ $pickerId }} .pp-toolbar { background: #17233a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-toolbar input[type="search"] { background: #1e293b; color: #e2e8f0; }
html[data-theme="dark"] #{{ $pickerId }} .pp-count { background: #1e293b; }
html[data-theme="dark"] #{{ $pickerId }} .pp-btn:hover { background: #22304a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-matrix { background: #1e293b; }
html[data-theme="dark"] #{{ $pickerId }} .pp-matrix thead { background: #17233a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-matrix th { background: #17233a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-group-meta { background: #1e293b; }
html[data-theme="dark"] #{{ $pickerId }} .pp-matrix tbody tr:nth-child(even) { background: #17233a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-matrix tbody tr.is-none .pp-matrix__count-pill { background: #22304a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-matrix__cell--na { background: #17233a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-matrix__cb-box { background: #1e293b; }
html[data-theme="dark"] #{{ $pickerId }} .pp-standalone { background: #1e293b; }
html[data-theme="dark"] #{{ $pickerId }} .pp-standalone__head { background: #17233a; }
html[data-theme="dark"] #{{ $pickerId }} .pp-ov-grant::after,
html[data-theme="dark"] #{{ $pickerId }} .pp-ov-revoke::after { box-shadow: 0 0 0 2px #1e293b; }
</style>
<script>
(function () {
    var root = document.getElementById(@json($pickerId));
    if (!root) return;
    var defAttr = root.getAttribute('data-role-defaults');
    if (defAttr === null) return;   // role form → no override overlay

    var defaults = {};
    try { (JSON.parse(defAttr) || []).forEach(function (id) { defaults[String(id)] = true; }); } catch (e) {}

    var boxes = function () { return root.querySelectorAll('input[type="checkbox"][value]'); };
    var countEl = root.querySelector('.pp-override-count');
    var nEl = root.querySelector('.pp-ov-n');
    var resetBtn = root.querySelector('.pp-reset-role');

    function mark() {
        var overrides = 0;
        boxes().forEach(function (b) {
            var isDefault = !!defaults[String(b.value)];
            var cell = b.closest('.pp-matrix__cell, .pp-standalone__item, label') || b.parentElement;
            b.classList.remove('pp-ov-grant', 'pp-ov-revoke');
            if (cell) cell.classList.remove('pp-ov-grant', 'pp-ov-revoke');
            if (b.checked !== isDefault) {
                overrides++;
                var cls = (b.checked && !isDefault) ? 'pp-ov-grant' : 'pp-ov-revoke';
                b.classList.add(cls);
                if (cell) cell.classList.add(cls);
                b.title = (cls === 'pp-ov-grant')
                    ? @json(__('Granted beyond the role')) : @json(__('Removed from the role'));
            } else {
                b.removeAttribute('title');
            }
        });
        if (countEl && nEl) { nEl.textContent = overrides; countEl.hidden = overrides === 0; }
    }

    root.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[type="checkbox"]')) mark();
    });
    // Bulk toggles (row/column/select-all/clear) don't bubble individual change
    // events reliably — re-mark shortly after any click inside the picker.
    root.addEventListener('click', function () { setTimeout(mark, 0); });

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            boxes().forEach(function (b) { b.checked = !!defaults[String(b.value)]; });
            // Nudge the picker's own counters, then re-mark.
            root.dispatchEvent(new Event('change', { bubbles: true }));
            mark();
        });
    }

    mark();  // initial paint (shows existing overrides on edit)
})();
</script>
