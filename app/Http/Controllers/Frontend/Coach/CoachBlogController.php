<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachBlog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Coach Blog Management (2026-06-23) — per-coach blog CRUD for the coach panel.
 * Every query is scoped to the OWNING coach (instructor acts as self; staff act
 * for their coach via coach_id), so a coach can only ever see/manage their own
 * posts. Published posts render on the coach's custom website (blog_v1 section
 * + the public blog detail page); drafts stay private.
 */
class CoachBlogController extends Controller
{
    /**
     * 2026-07-07 — server-side RBAC (same gap fixed on announcements). The blog
     * routes carried only `requires.membership`, so a staff member without the
     * blog permission could open the list/editor and POST to store/update/
     * destroy directly even though the buttons were hidden. Enforce the same
     * granular slugs the menu/buttons use. A real coach passes every check.
     */
    public function __construct()
    {
        $this->middleware('permission:blogs')->only(['index']);
        $this->middleware('permission:blogs-create')->only(['create', 'store']);
        $this->middleware('permission:blogs-edit')->only(['edit', 'update', 'toggleStatus']);
        $this->middleware('permission:blogs-delete')->only(['destroy']);
    }

    /** Owning coach id — tenant scope key. */
    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    public function index(Request $request)
    {
        $coachId = $this->coachId();
        $search  = trim((string) $request->get('search'));

        $blogs = CoachBlog::forCoach($coachId)
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('frontend.instructor-dashboard.blogs.index', compact('blogs', 'search'));
    }

    public function create()
    {
        $blog = new CoachBlog(['status' => CoachBlog::STATUS_DRAFT]);

        return view('frontend.instructor-dashboard.blogs.create', compact('blog'));
    }

    public function store(Request $request)
    {
        $data    = $this->validated($request);
        $coachId = $this->coachId();

        $blog = new CoachBlog();
        $blog->coach_id         = $coachId;
        $blog->title            = $data['title'];
        $blog->slug             = $this->uniqueSlug($coachId, $data['title']);
        $blog->short_description = $data['short_description'] ?? null;
        $blog->content          = $data['content'] ?? null;
        $blog->seo_title        = $data['seo_title'] ?? null;
        $blog->seo_description  = $data['seo_description'] ?? null;
        $blog->status           = $data['status'];
        $blog->published_at     = $this->resolvePublishedAt($data);

        if ($request->hasFile('image')) {
            $blog->image = file_upload($request->file('image'), 'uploads/coach-blogs/');
        }
        $blog->save();

        return redirect()->route('instructor.blogs.index')
            ->with(['messege' => __('Blog post created successfully.'), 'alert-type' => 'success']);
    }

    public function edit($id)
    {
        $blog = CoachBlog::forCoach($this->coachId())->findOrFail($id);

        return view('frontend.instructor-dashboard.blogs.edit', compact('blog'));
    }

    public function update(Request $request, $id)
    {
        $coachId = $this->coachId();
        $blog    = CoachBlog::forCoach($coachId)->findOrFail($id); // IDOR-safe
        $data    = $this->validated($request);

        // Keep the slug stable across edits (preserves SEO/permalinks) unless
        // the title actually changed — then regenerate a fresh unique slug.
        if ($data['title'] !== $blog->title) {
            $blog->slug = $this->uniqueSlug($coachId, $data['title'], $blog->id);
        }
        $blog->title            = $data['title'];
        $blog->short_description = $data['short_description'] ?? null;
        $blog->content          = $data['content'] ?? null;
        $blog->seo_title        = $data['seo_title'] ?? null;
        $blog->seo_description  = $data['seo_description'] ?? null;
        $blog->status           = $data['status'];
        $blog->published_at     = $this->resolvePublishedAt($data, $blog);

        if ($request->hasFile('image')) {
            $blog->image = file_upload($request->file('image'), 'uploads/coach-blogs/', $blog->image);
        }
        $blog->save();

        return redirect()->route('instructor.blogs.index')
            ->with(['messege' => __('Blog post updated successfully.'), 'alert-type' => 'success']);
    }

    public function destroy($id)
    {
        $blog = CoachBlog::forCoach($this->coachId())->findOrFail($id);

        if ($blog->image && \Illuminate\Support\Facades\File::exists(public_path($blog->image))) {
            try { \Illuminate\Support\Facades\File::delete(public_path($blog->image)); } catch (\Throwable $e) {}
        }
        $blog->delete();

        if (request()->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => __('Blog post deleted.')]);
        }

        return redirect()->route('instructor.blogs.index')
            ->with(['messege' => __('Blog post deleted.'), 'alert-type' => 'success']);
    }

    /** Quick draft/publish toggle from the list. */
    public function toggleStatus($id)
    {
        $blog = CoachBlog::forCoach($this->coachId())->findOrFail($id);
        $blog->status = $blog->status === CoachBlog::STATUS_PUBLISHED
            ? CoachBlog::STATUS_DRAFT
            : CoachBlog::STATUS_PUBLISHED;
        if ($blog->status === CoachBlog::STATUS_PUBLISHED && ! $blog->published_at) {
            $blog->published_at = now();
        }
        $blog->save();

        return redirect()->back()
            ->with(['messege' => __('Status updated.'), 'alert-type' => 'success']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'            => 'required|string|max:255',
            'short_description'=> 'nullable|string|max:1000',
            'content'          => 'nullable|string|max:200000',
            'image'            => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'seo_title'        => 'nullable|string|max:255',
            'seo_description'  => 'nullable|string|max:500',
            'status'           => 'required|in:draft,published',
            'published_at'     => 'nullable|date',
        ], [
            'title.required'  => __('Blog title is required.'),
            'status.required' => __('Please choose draft or published.'),
        ]);
    }

    /** Resolve publish date: explicit value wins; else stamp now on first publish. */
    private function resolvePublishedAt(array $data, ?CoachBlog $existing = null): ?\Illuminate\Support\Carbon
    {
        if (! empty($data['published_at'])) {
            return \Illuminate\Support\Carbon::parse($data['published_at']);
        }
        if ($data['status'] === CoachBlog::STATUS_PUBLISHED) {
            return $existing?->published_at ?? now();
        }
        return $existing?->published_at; // keep existing date when saving as draft
    }

    /** Unique slug within ONE coach's posts (optionally ignoring a row on edit). */
    private function uniqueSlug(int $coachId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $i = 1;
        while (CoachBlog::where('coach_id', $coachId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
