<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseChapterLesson;
use App\Models\CourseReview;
use App\Models\Quiz;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Course\app\Models\CourseCategory;
use Modules\Course\app\Models\CourseLanguage;
use Modules\Course\app\Models\CourseLevel;

class CoursePageController extends Controller
{
    function index() : View {
        $categories = CourseCategory::active()->whereNull('parent_id')->with(['translation'])->get();
        $languages = CourseLanguage::where('status', 1)->get();
        $levels = CourseLevel::where('status', 1)->with('translation')->get();
        return view('frontend.pages.course', compact('categories', 'languages', 'levels'));
    }

    function fetchCourses(Request $request) {
        $query = Course::query();
        $query->where(['is_approved' => 'approved', 'status' => 'active', 'coach_soft_delete' => 0]);
        // 2026-06-10 — TENANT SCOPE (defense-in-depth). On a coach domain the
        // catalog must list ONLY this coach's courses, never the platform-wide
        // catalog. resolved_coach_id is stamped by ResolveCoachByDomain and is
        // 0/null on the platform domain (then this is a no-op → unchanged).
        $tenantCoachId = (int) $request->attributes->get('resolved_coach_id');
        $query->when($tenantCoachId > 0, fn ($q) => $q->where('instructor_id', $tenantCoachId));
        // $query->whereHas('category.parentCategory', function($q) use ($request) {
        //     $q->where('status', 1);
        // }); 
         $query->whereHas('category', function($q) use ($request) {
            $q->where('status', 1);
        });

        $query->whereHas('category', function($q) use ($request) {
            $q->where('status', 1);
        });

        $query->whereHas('batches', function($q){
            $q->where('status', 'active')
            ->where('end_date', '>=', Carbon::today());
        });
            
        $query->when($request->search, function($q) use ($request) {
            $q->where('title', 'like', '%'.$request->search.'%');
        });
        $query->when($request->main_category, function($q) use ($request) {
            $q->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->main_category);
                // $q->whereHas('parentCategory', function($q) use ($request) {
                // });
            });
        });
        $query->when($request->category && $request->filled('category'), function($q) use ($request) {
            $categoriesIds = explode(',', $request->category);
            $q->whereIn('sub_category_id', $categoriesIds);
        });

        $query->when($request->language && $request->filled('language'), function($q) use ($request) {
            $languagesIds = explode(',', $request->language);
            $q->whereHas('languages', function($q) use ($languagesIds) {
                $q->whereIn('language_id', $languagesIds);
            });
        });

        $query->when($request->price, function($q) use ($request) {
            if($request->price == 'paid') {
                $q->where('price', '>', 0);
            }else {
                $q->where('price', 0)->orWhere('price', null);
            }
        });

        $query->when($request->level, function($q) use ($request) {
            $levelsIds = explode(',', $request->level);
            $q->whereHas('levels', function($q) use ($levelsIds) {
                $q->whereIn('level_id', $levelsIds);
            });
        });

        // F44 (audit 2026-06-26) — the card only needs the enrollment COUNT, so
        // use withCount instead of hydrating every enrollment row of every listed
        // course (thousands of rows/page on a popular catalog).
        $query->with(['instructor:id,name', 'category.translation'])->withCount('enrollments');

        $query->orderBy('created_at', $request->order && $request->filled('order') ? $request->order : 'desc');
        $courses = $query->paginate(6);

        $lastPage = $courses->lastPage();
        $page = $request->page ?? 1;
        $itemCount = $courses->count();

        $data = [
            'items' => view('frontend.partials.course-card', compact('courses'))->render(),
            'lastPage' => $lastPage,
            'currentPage' => $page,
            'itemCount' => $itemCount
        ];

        // if main category is selected then show sub category card
        if($request->main_category && $request->filled('main_category')) {
            $subCategories = CourseCategory::whereHas('parentCategory', function($q) use ($request) {
                $q->where('slug', $request->main_category);
            })->with('translation')->get();
            $categoriesIds = explode(',', $request->category);
            $data['sidebar_items'] = view('frontend.partials.course-sidebar-item', compact('subCategories', 'categoriesIds'))->render();
        }

        return response()->json($data);
    }

    function show(string $slug) {
        // 2026-06-10 — TENANT SCOPE (defense-in-depth). On a coach domain a
        // course detail page may only open for THIS coach's own course; a
        // competitor's slug 404s here (complementing the middleware redirect).
        // resolved_coach_id is 0/null on the platform domain → no-op.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $course = Course::active()->with(['chapters' => function($query) {
            $query->orderBy('order', 'asc')->with(['chapterItems', 'chapterItems.lesson', 'chapterItems.quiz']);
        }])
        ->withCount(['reviews' => function($query) {
            $query->where('status', 1)->whereHas('course')->whereHas('user');
        }])
        ->when($tenantCoachId > 0, fn ($q) => $q->where('instructor_id', $tenantCoachId))
        ->where('slug', $slug)->firstOrFail();
        $courseLessonCount = CourseChapterLesson::where('course_id', $course->id)->count();
        $courseBatchCount = CourseBatch::where('course_id', $course->id)->count();
        $courseQuizCount = Quiz::where('course_id', $course->id)->count();
        $reviews = CourseReview::where('course_id', $course->id)->where('status', 1)->whereHas('course')->whereHas('user')->orderBy('created_at', 'desc')->paginate(20);
        return view('frontend.pages.course-details', compact('course', 'courseLessonCount', 'courseBatchCount','courseQuizCount', 'reviews'));
    }
}
