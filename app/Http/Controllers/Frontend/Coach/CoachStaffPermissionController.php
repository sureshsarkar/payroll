<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachStaffPermission;
use Illuminate\Http\Request;

class CoachStaffPermissionController extends Controller
{
    protected $pageName;

    public function __construct(CoachStaffPermission $model)
    {
        $this->model = $model;
        $this->admin_base_url = 'instructor.coach-staff-permission.index';
        $this->admin_view = 'frontend.instructor-dashboard.coach-staff-permission';
        $this->admin_error_view = 'errors.403';
        $this->pageName = 'courses';
    }
    // return view('frontend.instructor-dashboard.coach-staff-role.index', compact('coachStaffRole'));

    public function index(Request $request)
    {
        // FT-IDOR-11 follow-up (2026-05-28) — was:
        //     $flag = checkPermission($this->pageName);
        //     $flag = 1;
        // i.e. the real call was made and then immediately overwritten,
        // making the result-check below dead code. Drop the override.
        //
        // NOTE: $this->pageName === 'courses' for this controller (see
        // ctor) which looks like a copy-paste artefact from a sibling
        // controller — staff with `courses` permission can reach this
        // page even though it manages permissions, not courses. Worth
        // a separate audit, but fixing the slug requires a seed update
        // so it's tracked in TODO comments rather than this commit.
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // 2026-05-20 v2 — IAM matrix rebuild.
        //
        // The original grouping split slugs on '.' which never matched
        // any real slug (all use '-'); every permission collapsed into
        // a single "general" bucket. Replaced with the same suffix-
        // based decomposition the _permission-picker partial uses:
        //   - bare slug                = (resource, access)
        //   - slug ending -show        = (resource, show)
        //   - slug ending -create      = (resource, create)
        //   - slug ending -edit        = (resource, edit)
        //   - slug ending -delete      = (resource, delete)
        // 8 real modules emerge from the 27 catalog entries.

        $matrixActions = ['access', 'show', 'create', 'edit', 'delete'];

        $allRows = $this->model::query()->orderBy('name')->get();

        // Search affects rendering visibility but NOT the matrix
        // skeleton — we still build the full structure first so the
        // KPI strip reflects the whole catalog, not a filtered slice.
        $q = trim((string) $request->get('q', ''));

        // ── Role-usage map (single query, no N+1) ─────────────────
        // For each permission, list the roles owned by THIS coach
        // that grant it. Stored as ['perm_id' => collection<{id,name}>]
        // so the side panel can render "Top used" + tooltip lists.
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id);
        $usageRows = \DB::table('roles_permissions as crp')
            ->join('coach_staff_roles as r', 'r.id', '=', 'crp.coach_staff_role_id')
            ->where('r.added_by', $coachId)
            ->select('crp.coach_staff_permission_id as pid', 'r.id as rid', 'r.role_name as rname')
            ->get();
        $usageByPerm = $usageRows->groupBy('pid')->map(function ($g) {
            return $g->map(fn ($r) => (object) ['id' => (int) $r->rid, 'name' => (string) $r->rname])
                     ->unique('id')->values();
        });

        // Attach roles + count to each permission row in place.
        $allRows->each(function ($r) use ($usageByPerm) {
            $r->granting_roles    = $usageByPerm[$r->id] ?? collect();
            $r->role_usage_count  = $r->granting_roles->count();
        });

        // ── Decompose slugs into (resource, action) pairs ────────
        $resourceMap = [];
        foreach ($allRows as $perm) {
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
            if (! $matched) {
                $resourceMap[$slug]['access'] = $perm;
            }
        }
        ksort($resourceMap);

        // Split: matrix rows (resources with >1 action OR any non-
        // access action) vs standalone (lone bare-slug resources).
        $matrixRows = [];
        $standalone = [];
        foreach ($resourceMap as $resource => $actions) {
            if (count($actions) === 1 && isset($actions['access'])) {
                $standalone[$resource] = $actions['access'];
            } else {
                $matrixRows[$resource] = $actions;
            }
        }

        // ── Per-row + per-column health computed once for the view ─
        $rowStats = [];
        foreach ($matrixRows as $resource => $actions) {
            $present = count($actions);
            // possible = how many actions THIS resource could
            // theoretically support. Today every matrix resource
            // could support all 5 in principle, so possible = 5;
            // we still compute per-row in case future migrations add
            // semantics like "this resource is access-only by design".
            $possible = 5;
            $used = collect($actions)->sum(fn ($p) => $p->role_usage_count > 0 ? 1 : 0);
            $rowStats[$resource] = [
                'present'  => $present,
                'possible' => $possible,
                'used'     => $used,
                'complete' => $present === $possible,
            ];
        }

        $colStats = [];
        foreach ($matrixActions as $action) {
            $present = 0;
            foreach ($matrixRows as $actions) {
                if (isset($actions[$action])) $present++;
            }
            $colStats[$action] = [
                'present' => $present,
                'total'   => count($matrixRows),
            ];
        }

        // ── Insight payloads for the side rail ────────────────────
        // Top 5 most-granted permissions (already have count attached).
        $topUsed = $allRows->where('role_usage_count', '>', 0)
            ->sortByDesc('role_usage_count')
            ->take(5)
            ->values();

        // Modules with gaps (present < possible) — coach gets a
        // "you might want to wire up X here" hint.
        $incompleteModules = collect($rowStats)
            ->filter(fn ($s) => $s['present'] < $s['possible'])
            ->map(fn ($s, $r) => ['resource' => $r] + $s)
            ->values();

        // Unused permissions list (separate from $kpi['orphaned']
        // count — the view shows BOTH the number and the list).
        $unusedPerms = $allRows->where('role_usage_count', 0)->values();

        $kpi = [
            'total'             => $allRows->count(),
            'modules'           => count($matrixRows) + count($standalone),
            'in_use'            => $allRows->where('role_usage_count', '>', 0)->count(),
            'orphaned'          => $unusedPerms->count(),
            'matrix_modules'    => count($matrixRows),
            'standalone_count'  => count($standalone),
            'incomplete_count'  => $incompleteModules->count(),
            'distinct_roles'    => $usageRows->pluck('rid')->unique()->count(),
        ];

        // Module chips for the filter row — emitted alphabetically.
        $moduleChips = array_keys($matrixRows);

        // Legacy variables kept so any external blade include or
        // partial that referenced them keeps working.
        $data    = $allRows;
        $grouped = collect($matrixRows)->mapWithKeys(fn ($a, $r) => [$r => collect($a)->values()]);

        return view($this->admin_view.'.index', compact(
            'matrixRows', 'standalone', 'matrixActions',
            'rowStats', 'colStats',
            'topUsed', 'incompleteModules', 'unusedPerms',
            'moduleChips', 'kpi', 'q',
            'data', 'grouped',
        ));
    }

    /**
     * SECURITY (audit 2026-06-12) — `coach_staff_permissions` is a GLOBAL,
     * platform-defined catalog (no coach_id). A coach must NEVER create/edit/
     * delete catalog rows: doing so corrupts EVERY other coach's roles (the
     * roles_permissions pivot references these shared ids). Coaches only ASSIGN
     * existing permissions to their own staff roles (CoachStaffRoleController,
     * which is coach-scoped). Today only the read-only `index` route is wired,
     * but these write methods are hardened to refuse so they can never become a
     * cross-tenant foot-gun if a route is added later. Catalog management is an
     * admin/superadmin responsibility.
     */
    public function create()
    {
        abort(403, __('The staff permission catalog is managed by the platform administrator.'));
    }

    public function store(Request $request)
    {
        abort(403, __('The staff permission catalog is managed by the platform administrator.'));
    }

    public function show($id)
    {
        // FT-IDOR-11 fix (2026-05-28) — see above.
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            return redirect()->route($this->admin_base_url);
        } else {
            return view($this->admin_error_view);
        }
    }

    public function edit($id)
    {
        abort(403, __('The staff permission catalog is managed by the platform administrator.'));
    }

    public function update(Request $request, $id)
    {
        abort(403, __('The staff permission catalog is managed by the platform administrator.'));
    }

    public function destroy($id)
    {
        abort(403, __('The staff permission catalog is managed by the platform administrator.'));
    }
}
