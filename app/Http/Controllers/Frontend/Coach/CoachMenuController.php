<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachLandingPage;
use App\Models\CoachMenu;
use App\Models\CoachMenuItem;
use App\Models\CoachPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Per-coach NAVIGATION MENU builder (2026-06-17).
 *
 * Fully tenant-scoped: every read/write is constrained to the logged-in coach's
 * own menu. Supports add / edit / delete / reorder (with one level of
 * dropdowns), and items that link to an internal page, external URL, page
 * section (anchor), named route, or the site home.
 *
 * A coach with `is_active=false` (or no menu at all) falls back to the legacy
 * page-derived navigation — so this is non-breaking for everyone who never
 * opens the builder.
 */
class CoachMenuController extends Controller
{
    // ---------------- UI ----------------

    public function index()
    {
        if (! $this->permitted()) {
            return view('errors.403');
        }
        $coachId = $this->coachId();
        $menu    = CoachMenu::primaryForCoach($coachId);
        if (! $menu->exists) {
            $menu->save(); // lazily create the primary menu on first visit
        }

        $items = CoachMenuItem::where('menu_id', $menu->id)
            ->orderBy('sort_order')
            ->get();

        // Flatten into a pre-ordered list with depth (1=top, 2=column/link,
        // 3=mega-column link) so the builder can render a single sortable list
        // and rebuild the tree from indent levels. Mega menus use all 3 levels.
        $byParent = $items->groupBy(fn ($i) => $i->parent_id ?? 0);
        $flat = [];
        $walk = function ($parentKey, int $depth) use (&$walk, $byParent, &$flat) {
            foreach ($byParent->get($parentKey, collect()) as $node) {
                $flat[] = ['item' => $node, 'depth' => $depth];
                if ($depth < 3) {
                    $walk($node->id, $depth + 1);
                }
            }
        };
        $walk(0, 1);
        $tree = collect($flat);

        $pages = CoachPage::forCoach($coachId)
            ->orderByRaw("CASE WHEN page_type='home' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'page_type']);

        $site = CoachLandingPage::where('added_by', $coachId)->first();

        return view('frontend.instructor-dashboard.coach-site.menu-builder', [
            'menu'  => $menu,
            'tree'  => $tree,
            'pages' => $pages,
            'site'  => $site,
        ]);
    }

    // ---------------- CRUD ----------------

    public function storeItem(Request $request)
    {
        if (! $this->permitted()) {
            return response()->json(['ok' => false], 403);
        }
        $coachId = $this->coachId();
        $menu    = CoachMenu::primaryForCoach($coachId);
        if (! $menu->exists) {
            $menu->save();
        }

        $data = $this->validateItem($request, $coachId, $menu->id);

        $data['coach_id']   = $coachId;
        $data['menu_id']    = $menu->id;
        $data['sort_order'] = (int) CoachMenuItem::where('menu_id', $menu->id)
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort_order') + 1;

        $item = CoachMenuItem::create($data);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function updateItem(Request $request, int $id)
    {
        if (! $this->permitted()) {
            return response()->json(['ok' => false], 403);
        }
        $coachId = $this->coachId();
        $item    = $this->findOwnedItem($id, $coachId);

        $data = $this->validateItem($request, $coachId, $item->menu_id, $item->id);
        $item->update($data);

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    public function deleteItem(int $id)
    {
        if (! $this->permitted()) {
            return response()->json(['ok' => false], 403);
        }
        $coachId = $this->coachId();
        $item    = $this->findOwnedItem($id, $coachId);
        $item->delete(); // children bubble to top level (nullOnDelete)

        return response()->json(['ok' => true]);
    }

    /**
     * Drag-drop reorder + re-nest. Payload: items = ordered array of
     * { id, children: [{ id }] }. Only 2 levels (dropdowns). Every id is
     * verified to belong to this coach's menu before any write.
     */
    public function reorder(Request $request)
    {
        if (! $this->permitted()) {
            return response()->json(['ok' => false], 403);
        }
        $coachId = $this->coachId();
        $menu    = CoachMenu::primaryForCoach($coachId);
        if (! $menu->exists) {
            return response()->json(['ok' => true]); // nothing to reorder
        }

        $payload = $request->input('items', []);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'items must be an array'], 422);
        }

        // The set of ids this coach is allowed to touch.
        $ownedIds = CoachMenuItem::where('menu_id', $menu->id)->pluck('id')->all();
        $ownedSet = array_flip($ownedIds);

        // Up to 3 levels for mega menus: trigger → columns → links. Dropdowns
        // use the first 2. Every id is verified against $ownedSet before write.
        DB::transaction(function () use ($payload, $menu, $ownedSet) {
            $top = 0;
            foreach ($payload as $node) {
                $topId = (int) ($node['id'] ?? 0);
                if (! isset($ownedSet[$topId])) {
                    continue;
                }
                CoachMenuItem::where('id', $topId)->where('menu_id', $menu->id)
                    ->update(['parent_id' => null, 'sort_order' => $top++]);

                $child = 0;
                foreach (($node['children'] ?? []) as $c) {
                    $childId = (int) ($c['id'] ?? 0);
                    if (! isset($ownedSet[$childId]) || $childId === $topId) {
                        continue;
                    }
                    CoachMenuItem::where('id', $childId)->where('menu_id', $menu->id)
                        ->update(['parent_id' => $topId, 'sort_order' => $child++]);

                    // Level 3 — mega-column links.
                    $grand = 0;
                    foreach (($c['children'] ?? []) as $g) {
                        $grandId = (int) ($g['id'] ?? 0);
                        if (! isset($ownedSet[$grandId]) || $grandId === $childId || $grandId === $topId) {
                            continue;
                        }
                        CoachMenuItem::where('id', $grandId)->where('menu_id', $menu->id)
                            ->update(['parent_id' => $childId, 'sort_order' => $grand++]);
                    }
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Enable / disable the custom menu (disabled → page-derived nav). */
    public function toggleMenu(Request $request)
    {
        if (! $this->permitted()) {
            return response()->json(['ok' => false], 403);
        }
        $coachId = $this->coachId();
        $menu    = CoachMenu::primaryForCoach($coachId);
        $menu->is_active = $request->boolean('is_active');
        $menu->coach_id  = $coachId;
        $menu->save();

        return response()->json(['ok' => true, 'is_active' => $menu->is_active]);
    }

    // ---------------- helpers ----------------

    private function validateItem(Request $request, int $coachId, int $menuId, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'label'          => ['required', 'string', 'max:80'],
            'link_type'      => ['required', Rule::in(CoachMenuItem::LINK_TYPES)],
            'page_id'        => ['nullable', 'integer'],
            'url'            => ['nullable', 'string', 'max:600'],
            'section_anchor' => ['nullable', 'string', 'max:120'],
            'route_name'     => ['nullable', 'string', 'max:120'],
            'target'         => ['nullable', Rule::in(['_self', '_blank'])],
            'parent_id'      => ['nullable', 'integer'],
            'is_visible'     => ['sometimes', 'boolean'],
            'layout'         => ['nullable', Rule::in(CoachMenuItem::LAYOUTS)],
        ]);

        // page_id must be one of THIS coach's pages.
        if (! empty($validated['page_id'])) {
            $ok = CoachPage::forCoach($coachId)->where('id', $validated['page_id'])->exists();
            if (! $ok) {
                abort(422, 'Invalid page selection.');
            }
        }

        // parent_id must belong to the SAME menu, never the item itself, and
        // nesting is capped at 3 levels (trigger → column → link for mega; a
        // dropdown only uses 2). So a parent may be level-1 or level-2, but not
        // already level-3.
        if (! empty($validated['parent_id'])) {
            $parent = CoachMenuItem::where('id', $validated['parent_id'])
                ->where('menu_id', $menuId)
                ->first();
            $parentIsLevel3 = $parent && $parent->parent_id !== null
                && CoachMenuItem::where('id', $parent->parent_id)->value('parent_id') !== null;
            if (! $parent || $parentIsLevel3 || ($ignoreId && (int) $validated['parent_id'] === $ignoreId)) {
                abort(422, 'Invalid parent menu item.');
            }
        }

        // type-specific required field
        $type = $validated['link_type'];
        if ($type === 'url' && empty($validated['url'])) {
            abort(422, 'A URL is required for a custom link.');
        }
        if ($type === 'page' && empty($validated['page_id'])) {
            abort(422, 'Please choose a page.');
        }
        if ($type === 'route' && empty($validated['route_name'])) {
            abort(422, 'A route name is required.');
        }

        $validated['target']     = $validated['target'] ?? '_self';
        $validated['is_visible'] = $request->boolean('is_visible', true);
        $validated['parent_id']  = $validated['parent_id'] ?? null;
        // 'mega' is only meaningful on a top-level item; children are always 'dropdown'.
        $validated['layout'] = empty($validated['parent_id'])
            ? ($validated['layout'] ?? 'dropdown')
            : 'dropdown';

        return $validated;
    }

    private function findOwnedItem(int $id, int $coachId): CoachMenuItem
    {
        return CoachMenuItem::where('id', $id)
            ->where('coach_id', $coachId)
            ->firstOrFail();
    }

    private function permitted(): bool
    {
        return checkPermission('landing-page-builder') == 1;
    }

    private function coachId(): int
    {
        return (int) (userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id);
    }
}
