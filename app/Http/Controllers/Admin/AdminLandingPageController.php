<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachLandingPage;
use App\Models\LandingPageEnquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\PageTemplateBuilder\app\Models\PageTemplateCategory;

/**
 * Admin oversight of all coach-owned landing pages — added 2026-05-12 as
 * Phase 5 of the multi-template business website system.
 *
 * Pre-this-controller, there was no cross-coach view of published landing
 * pages. Admins could see templates (via the PageTemplateBuilder module)
 * and individual coaches' enquiries one-coach-at-a-time, but couldn't
 * answer questions like:
 *   - "Which templates are most popular?"
 *   - "Which coaches actually publish their pages?"
 *   - "Where are leads coming from across the platform?"
 *
 * Routes (in routes/admin.php under the auth:admin+2fa group):
 *   GET admin/coach-landing-pages                → index()
 *   GET admin/coach-landing-pages/{id}/enquiries → enquiries()
 */
class AdminLandingPageController extends Controller
{
    /**
     * Allowed sort keys → DB columns. We allowlist to avoid building an
     * ORDER BY from raw request input. Mirrors the pattern used by
     * LandingPageEnquiryController on the coach side.
     */
    private const SORTABLE = [
        'created_at'     => 'coach_landing_pages.created_at',
        'name'           => 'coach_landing_pages.website_name',
        'subdomain'      => 'coach_landing_pages.subdomain',
        'coach'          => 'users.name',
        'enquiries'      => 'enquiry_count',
    ];

    public function index(Request $request): View
    {
        checkAdminHasPermissionAndThrowException('coach-landing-page.view');

        // -------- Base query: pages × coach × template × category --------
        // LEFT JOIN on template/category so pages without a valid template
        // (legacy rows) still appear in the list; we just show "—" for that
        // column in the view.
        $q = CoachLandingPage::query()
            ->select([
                'coach_landing_pages.*',
                'users.name as coach_name',
                'users.email as coach_email',
                'page_template_builders.template_name as template_name',
                'page_template_builders.image as template_image',
                'page_template_categories.name as business_category',
                DB::raw('(SELECT COUNT(*) FROM landing_page_enquiries WHERE landing_page_enquiries.landing_page_id = coach_landing_pages.id) as enquiry_count'),
                DB::raw('(SELECT MAX(created_at) FROM landing_page_enquiries WHERE landing_page_enquiries.landing_page_id = coach_landing_pages.id) as last_enquiry_at'),
            ])
            ->leftJoin('users', 'users.id', '=', 'coach_landing_pages.added_by')
            ->leftJoin('page_template_builders', 'page_template_builders.id', '=', 'coach_landing_pages.template_id')
            ->leftJoin('page_template_categories', 'page_template_categories.id', '=', 'page_template_builders.category');

        // -------- Filters --------
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($w) use ($like) {
                $w->where('coach_landing_pages.website_name', 'like', $like)
                  ->orWhere('coach_landing_pages.subdomain',  'like', $like)
                  ->orWhere('coach_landing_pages.slug',       'like', $like)
                  ->orWhere('users.name',                     'like', $like)
                  ->orWhere('users.email',                    'like', $like);
            });
        }

        $category = (string) $request->query('category', '');
        if ($category !== '') {
            $q->where('page_template_categories.name', $category);
        }

        $published = $request->query('published', '');
        if ($published === '1' || $published === '0') {
            $q->where('coach_landing_pages.is_published', (int) $published);
        }

        // -------- Sort (allowlist) --------
        $sortKey = (string) $request->query('sort', 'created_at');
        $sortCol = self::SORTABLE[$sortKey] ?? 'coach_landing_pages.created_at';
        $sortDir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortCol, $sortDir);

        $pages = $q->paginate(15)->withQueryString();

        // -------- Stats banner --------
        // Single round-trip aggregate so the admin sees the headline numbers
        // without us hammering the DB.
        $stats = DB::table('coach_landing_pages')
            ->selectRaw('COUNT(*) AS total_pages')
            ->selectRaw('SUM(is_published = 1) AS published_pages')
            ->selectRaw('COUNT(DISTINCT added_by) AS distinct_coaches')
            ->first();

        $totalEnquiries = (int) LandingPageEnquiry::count();
        $leadsThisWeek  = (int) LandingPageEnquiry::where('created_at', '>=', now()->subDays(7))->count();

        $categories = PageTemplateCategory::where('status', 1)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return view('admin.landing-pages.index', [
            'pages'          => $pages,
            'stats'          => $stats,
            'totalEnquiries' => $totalEnquiries,
            'leadsThisWeek'  => $leadsThisWeek,
            'categories'     => $categories,
            'filters'        => [
                'q'         => $search,
                'category'  => $category,
                'published' => $published,
                'sort'      => $sortKey,
                'dir'       => $sortDir,
            ],
        ]);
    }

    /**
     * Drill-down: all enquiries received by one landing page. Useful when
     * admin sees a high enquiry_count and wants to inspect the actual
     * conversations without impersonating the coach.
     */
    public function enquiries(int $id, Request $request): View
    {
        checkAdminHasPermissionAndThrowException('coach-landing-page.view');

        $page = CoachLandingPage::with(['coach', 'getTemplate.categoryname'])
            ->findOrFail($id);

        $enquiries = LandingPageEnquiry::where('landing_page_id', $id)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.landing-pages.enquiries', compact('page', 'enquiries'));
    }

    /**
     * Per-template performance report — Phase 7 of the multi-template
     * business website system (2026-05-12). Answers questions admins
     * couldn't answer before:
     *
     *   - "Which templates do coaches actually pick?"
     *   - "Which templates produce the most leads?"
     *   - "Which templates have stalled — adopted but no conversions?"
     *
     * Three metrics per template:
     *   - coaches  → DISTINCT count of added_by on pages using this template
     *   - pages    → total CoachLandingPage rows using this template
     *   - leads    → total LandingPageEnquiry rows attributed to it
     *   - leads_30 → subset of leads in the last 30 days (recent activity)
     *
     * All four computed in one query via LEFT JOIN + conditional aggregate
     * so the report scales to thousands of templates without N+1.
     */
    public function templatePerformance(Request $request): View
    {
        checkAdminHasPermissionAndThrowException('coach-landing-page.view');

        $sortKey = (string) $request->query('sort', 'leads');
        $sortMap = [
            'leads'    => 'leads',
            'leads_30' => 'leads_30',
            'coaches'  => 'coaches',
            'pages'    => 'pages',
            'name'     => 'template_name',
        ];
        $sortCol = $sortMap[$sortKey] ?? 'leads';
        $sortDir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Single round-trip aggregate: every active template + its usage
        // counts. LEFT JOIN so a never-picked template still appears in
        // the list (with zeros) — useful for admin to retire dead templates.
        $rows = \Illuminate\Support\Facades\DB::table('page_template_builders as t')
            ->leftJoin('page_template_categories as c', 'c.id', '=', 't.category')
            ->leftJoin('coach_landing_pages as p', 'p.template_id', '=', 't.id')
            ->leftJoin('landing_page_enquiries as e', 'e.template_id', '=', 't.id')
            ->where('t.status', 1)
            ->groupBy('t.id', 't.template_name', 't.image', 'c.name')
            ->select([
                't.id',
                't.template_name',
                't.image',
                'c.name as category_name',
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT p.added_by) as coaches'),
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT p.id) as pages'),
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT e.id) as leads'),
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT CASE WHEN e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN e.id END) as leads_30'),
            ])
            ->orderBy($sortCol, $sortDir)
            ->get();

        // Headline numbers for the stat band — show "engaged" (coaches > 0)
        // separately from total so admin sees adoption gap at a glance.
        $totalTemplates    = $rows->count();
        $adoptedTemplates  = $rows->where('coaches', '>', 0)->count();
        $totalLeads        = (int) $rows->sum('leads');
        $totalLeads30      = (int) $rows->sum('leads_30');

        return view('admin.landing-pages.template-performance', [
            'rows'             => $rows,
            'totalTemplates'   => $totalTemplates,
            'adoptedTemplates' => $adoptedTemplates,
            'totalLeads'       => $totalLeads,
            'totalLeads30'     => $totalLeads30,
            'sort'             => $sortKey,
            'dir'              => $sortDir,
        ]);
    }
}
