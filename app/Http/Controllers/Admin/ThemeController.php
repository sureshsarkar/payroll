<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemeCategory;
use App\Models\User;
use App\Services\Theme\ThemeApplicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Super Admin Theme Studio — catalog + CRUD + enable/disable + preview.
 *
 * Routes:
 *   GET    /admin/themes                       → list + filter
 *   GET    /admin/themes/create                → new theme form
 *   POST   /admin/themes                       → store
 *   GET    /admin/themes/{id}/edit             → edit form
 *   PUT    /admin/themes/{id}                  → update
 *   POST   /admin/themes/{id}/toggle           → enable/disable
 *   POST   /admin/themes/{id}/duplicate        → clone
 *   DELETE /admin/themes/{id}                  → soft delete (blocked when in use)
 *   GET    /admin/themes/{id}/preview          → live preview with placeholder brand
 *   GET    /admin/themes/{id}/usage            → list coaches using this theme
 *
 * Audit-logged via theme_audit_log table.
 */
class ThemeController extends Controller
{
    // ── List ─────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('theme.view');
        $q = Theme::query()->with('categories');

        if ($request->filled('search')) {
            $q->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('status')) {
            $q->where('is_enabled', $request->status === 'enabled');
        }
        if ($request->filled('category')) {
            $q->whereHas('categories', fn ($x) => $x->where('slug', $request->category));
        }

        $themes = $q->orderBy('sort_order')->orderByDesc('id')->paginate(20);

        // Pre-compute active usage per theme (one query)
        $usage = \DB::table('theme_applications')
            ->whereNull('superseded_at')
            ->select('theme_id', DB::raw('COUNT(*) AS uses'))
            ->groupBy('theme_id')
            ->pluck('uses', 'theme_id');

        $categories = ThemeCategory::orderBy('sort_order')->get();

        return view('admin.theme-studio.index', compact('themes', 'usage', 'categories'));
    }

    // ── Create / Store ───────────────────────────────────────────────

    public function create()
    {
        checkAdminHasPermissionAndThrowException('theme.create');
        $categories = ThemeCategory::orderBy('sort_order')->get();
        return view('admin.theme-studio.create', compact('categories'));
    }

    public function store(Request $request)
    {
        checkAdminHasPermissionAndThrowException('theme.create');
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'slug'              => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9-]+$/', 'unique:themes,slug'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'thumbnail_url'     => ['nullable', 'string', 'max:500'],
            'primary_color'     => ['nullable', 'string', 'max:20'],
            'accent_color'      => ['nullable', 'string', 'max:20'],
            'display_font'      => ['nullable', 'string', 'max:60'],
            'body_font'         => ['nullable', 'string', 'max:60'],
            'is_premium'        => ['sometimes', 'boolean'],
            'categories'        => ['nullable', 'array'],
            'categories.*'      => ['integer', 'exists:theme_categories,id'],
        ]);

        $theme = Theme::create([
            'name'           => $data['name'],
            'slug'           => $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(4),
            'description'    => $data['description']  ?? null,
            'thumbnail_url'  => $data['thumbnail_url'] ?? null,
            'default_colors' => [
                'primary' => $data['primary_color'] ?? '#6366F1',
                'accent'  => $data['accent_color']  ?? '#8B5CF6',
            ],
            'default_fonts'  => [
                'display' => $data['display_font'] ?? 'Plus Jakarta Sans',
                'body'    => $data['body_font']    ?? 'Inter',
            ],
            'is_enabled'     => false,    // disabled by default — admin enables after content
            'is_premium'     => $data['is_premium'] ?? false,
            'version'        => '1.0',
            'sort_order'     => (int) Theme::max('sort_order') + 1,
            'author_user_id' => auth()->id(),
        ]);

        if (! empty($data['categories'])) {
            $theme->categories()->sync($data['categories']);
        }

        $this->audit('create', $theme, $data);

        return redirect()->route('admin.themes.edit', $theme->id)
            ->with('success', __('Theme created. Add pages and sections, then enable.'));
    }

    // ── Edit / Update ────────────────────────────────────────────────

    public function edit(int $id)
    {
        checkAdminHasPermissionAndThrowException('theme.update');
        $theme = Theme::with(['categories', 'pages.sections'])->findOrFail($id);
        $categories = ThemeCategory::orderBy('sort_order')->get();
        return view('admin.theme-studio.edit', compact('theme', 'categories'));
    }

    public function update(Request $request, int $id)
    {
        checkAdminHasPermissionAndThrowException('theme.update');
        $theme = Theme::findOrFail($id);

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'thumbnail_url'     => ['nullable', 'string', 'max:500'],
            'primary_color'     => ['nullable', 'string', 'max:20'],
            'accent_color'      => ['nullable', 'string', 'max:20'],
            'display_font'      => ['nullable', 'string', 'max:60'],
            'body_font'         => ['nullable', 'string', 'max:60'],
            'is_premium'        => ['sometimes', 'boolean'],
            'categories'        => ['nullable', 'array'],
            'categories.*'      => ['integer', 'exists:theme_categories,id'],
        ]);

        $theme->update([
            'name'           => $data['name'],
            'description'    => $data['description']  ?? null,
            'thumbnail_url'  => $data['thumbnail_url'] ?? null,
            'default_colors' => [
                'primary' => $data['primary_color'] ?? '#6366F1',
                'accent'  => $data['accent_color']  ?? '#8B5CF6',
            ],
            'default_fonts'  => [
                'display' => $data['display_font'] ?? 'Plus Jakarta Sans',
                'body'    => $data['body_font']    ?? 'Inter',
            ],
            'is_premium'     => $data['is_premium'] ?? false,
        ]);

        $theme->categories()->sync($data['categories'] ?? []);

        $this->audit('update', $theme, $data);

        return redirect()->back()->with('success', __('Theme updated.'));
    }

    // ── Toggle enable / disable ──────────────────────────────────────

    public function toggle(int $id)
    {
        // AUD-004 (audit 2026-07-16): this endpoint enables/disables a theme
        // platform-wide and shipped with NO permission gate. Admin RBAC is
        // deny-by-default with no super-admin wildcard, so it was genuinely
        // reachable by every authenticated admin.
        checkAdminHasPermissionAndThrowException('theme.update');

        $theme = Theme::findOrFail($id);
        $theme->update(['is_enabled' => ! $theme->is_enabled]);
        $this->audit($theme->is_enabled ? 'enable' : 'disable', $theme);
        return response()->json([
            'ok'         => true,
            'is_enabled' => $theme->is_enabled,
        ]);
    }

    // ── Duplicate ────────────────────────────────────────────────────

    public function duplicate(int $id)
    {
        // AUD-005 (audit 2026-07-16): clones a theme with all its pages and
        // sections — a create path — but had no gate, while create/store both
        // require theme.create.
        checkAdminHasPermissionAndThrowException('theme.create');

        $source = Theme::with('pages.sections', 'categories')->findOrFail($id);

        $clone = DB::transaction(function () use ($source) {
            $copy = $source->replicate(['id', 'created_at', 'updated_at']);
            $copy->name = $source->name . ' (Copy)';
            $copy->slug = $source->slug . '-copy-' . Str::lower(Str::random(4));
            $copy->is_enabled = false;
            $copy->version = '1.0';
            $copy->save();

            $copy->categories()->sync($source->categories->pluck('id')->toArray());

            foreach ($source->pages as $page) {
                $newPage = $copy->pages()->create($page->only([
                    'slug', 'page_type', 'title', 'meta_title',
                    'meta_description', 'sort_order', 'is_required',
                ]));
                foreach ($page->sections as $section) {
                    $newPage->sections()->create($section->only([
                        'section_type', 'section_version', 'content_json',
                        'sort_order', 'is_required',
                    ]));
                }
            }
            return $copy;
        });

        $this->audit('duplicate', $clone, ['source_id' => $source->id]);
        return redirect()->route('admin.themes.edit', $clone->id)
            ->with('success', __('Theme duplicated.'));
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        checkAdminHasPermissionAndThrowException('theme.delete');
        $theme = Theme::findOrFail($id);
        $inUse = $theme->activeUsageCount();
        if ($inUse > 0) {
            return back()->with('error', __("Cannot delete — {$inUse} coach(es) are currently using this theme."));
        }
        $theme->delete();
        $this->audit('delete', $theme);
        return redirect()->route('admin.themes.index')->with('success', __('Theme deleted.'));
    }

    // ── Live Preview (with placeholder brand) ────────────────────────

    public function preview(int $id, Request $request)
    {
        // Read-only, but it renders unreleased theme content — same gate as index.
        checkAdminHasPermissionAndThrowException('theme.view');

        $theme = Theme::with('pages.sections')->findOrFail($id);
        $pageSlug = $request->query('page', 'home');

        $themePage = $theme->pages()->where('slug', $pageSlug)->first()
            ?? $theme->pages()->first();
        if (! $themePage) abort(404);

        // Build a synthetic User + Brand for preview
        $stub = new User([
            'name' => 'Demo Coach',
            'email' => 'demo@example.com',
            'phone' => '+91 98765 43210',
            'id' => 0,
        ]);
        $stub->id = 0;

        // Run tokens through the theme content
        $resolver = app(\App\Services\Theme\ThemeTokenResolver::class);
        $brand = (object) [
            'name'           => $theme->name . ' Demo',
            'logo'           => null,
            'primary_color'  => $theme->default_colors['primary'] ?? '#6366F1',
            'accent_color'   => $theme->default_colors['accent']  ?? '#8B5CF6',
            'support_email'  => 'demo@example.com',
        ];

        $previewSections = $themePage->sections->map(function ($s) use ($resolver, $stub) {
            return (object) [
                'id'              => $s->id,
                'section_type'    => $s->section_type,
                'section_version' => $s->section_version,
                'content_json'    => $resolver->resolve(
                    (array) ($s->content_json ?? []),
                    $stub,
                    ['brand_name' => 'Demo Coach', 'coach_name' => 'Demo Coach']
                ),
                'sort_order'      => $s->sort_order,
                'is_visible'      => true,
            ];
        });

        // Render each section with appearance style
        $renderer = app(\App\Services\Site\SectionRenderer::class);
        $body = '';
        foreach ($previewSections as $sec) {
            $secModel = new \App\Models\CoachPageSection((array) $sec);
            $secModel->id = $sec->id;
            try {
                $body .= $renderer->renderOne($secModel, new \App\Models\CoachPage([
                    'title' => $themePage->title,
                    'slug'  => $themePage->slug,
                ]), $stub);
            } catch (\Throwable $e) {
                $body .= '<!-- section render skipped: ' . htmlspecialchars($e->getMessage()) . ' -->';
            }
        }

        return view('frontend.coach-site.layouts.master', [
            'page'  => new \App\Models\CoachPage([
                'title' => $themePage->title,
                'slug'  => $themePage->slug,
                'meta_title' => $themePage->meta_title,
                'meta_description' => $themePage->meta_description,
                'robots' => 'noindex',   // preview should never be indexed
                'is_published' => true,
            ]),
            'site'  => null,
            'coach' => $stub,
            'brand' => $brand,
            'siteNav' => [],
            'bodyHtml' => $body,
            'hasFooterSection' => false,
        ]);
    }

    // ── Usage ────────────────────────────────────────────────────────

    public function usage(int $id)
    {
        // Read-only, but lists every coach on the theme (name + email) — gate it.
        checkAdminHasPermissionAndThrowException('theme.view');

        $theme = Theme::findOrFail($id);
        $applications = \App\Models\ThemeApplication::where('theme_id', $id)
            ->with('coach:id,name,email')
            ->orderByDesc('applied_at')
            ->paginate(30);
        return view('admin.theme-studio.usage', compact('theme', 'applications'));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function audit(string $action, Theme $theme, $payload = []): void
    {
        \DB::table('theme_audit_log')->insert([
            'admin_user_id' => auth()->id() ?? 0,
            'theme_id'      => $theme->id,
            'action'        => $action,
            'payload'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ip'            => request()->ip(),
            'created_at'    => now(),
        ]);
    }
}
