<?php

namespace Modules\Course\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\CoursePartnerInstructor;
use App\Models\CourseSelectedLanguage;
use App\Models\CourseSelectedLevel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Modules\Course\app\Http\Requests\CourseStoreRequest;
use Modules\Course\app\Models\CourseCategory;
use Modules\Course\app\Models\CourseLanguage;
use Modules\Course\app\Models\CourseLevel;
use Modules\Order\app\Models\OrderItem;

class CourseController extends Controller {
    function index(Request $request): View {
        // FT-IDOR-27 fix (2026-05-28) — was missing. 7 of 11 methods
        // in this controller lacked the gate that storeMoreInfo /
        // storeFinish / destroy already use. Without it a sub-admin
        // without `course.management` (e.g. a Content Editor) could:
        //   • list every course on the platform (index),
        //   • render the new-course / edit forms (create / editView),
        //   • CREATE new courses and assign instructor_id to ANY
        //     instructor on the platform (store),
        //   • multi-step edit any course (edit / update),
        //   • toggle status='active' on courses awaiting review
        //     (statusUpdate).
        // The biggest blast radius is store(): it can both create
        // NEW courses attributed to a victim instructor (the victim
        // gets revenue-share commission on sales of a course they
        // never authored) and REASSIGN existing courses (line 66
        // sets instructor_id from $request->instructor on edit_mode).
        checkAdminHasPermissionAndThrowException('course.management');

        $query = Course::query();
        $query->when($request->keyword, fn($q) => $q->where('title', 'like', '%' . request('keyword') . '%'));
        $query->when($request->category, function ($q) use ($request) {
            $q->whereHas('category', function ($q) use ($request) {
                $q->where('id', $request->category);
            });
        });
        $query->when($request->date && $request->filled('date'), fn($q) => $q->whereDate('created_at', $request->date));
        $query->when($request->approve_status && $request->filled('approve_status'), fn($q) => $q->where('is_approved', $request->approve_status));
        $query->when($request->status && $request->filled('status'), fn($q) => $q->where('status', $request->status));
        $query->when($request->instructor && $request->filled('instructor'), function ($q) use ($request) {
            $q->where('instructor_id', $request->instructor);
        });
        $query->withCount('enrollments');
        $query->withCount('batches');
        $orderBy = $request->order_by == 1 ? 'asc' : 'desc';
        $courses = $request->par_page == 'all' ?
        $query->orderBy('id', $orderBy)->get() :
        $query->orderBy('id', $orderBy)->paginate($request->par_page ?? null)->withQueryString();
        $categories = CourseCategory::where('status', 1)->get();
       
        $instructors = User::where('role', 'instructor')->get();
        return view('course::course.index', compact('courses', 'categories', 'instructors'));
    }

    function create() {
        // FT-IDOR-27 fix — see index().
        checkAdminHasPermissionAndThrowException('course.management');
        $instructors = User::where('role', 'instructor')->get();
        return view('course::course.create', compact('instructors'));
    }

    function editView(string $id) {
        // FT-IDOR-27 fix — see index().
        checkAdminHasPermissionAndThrowException('course.management');
        Session::put('course_create', $id);
        $course = Course::findOrFail($id);
        $instructors = User::where('role', 'instructor')->get();
        $editMode = true;
        return view('course::course.create', compact('course', 'editMode', 'instructors'));
    }

    function store(CourseStoreRequest $request) {
        // FT-IDOR-27 fix — see index(). store() is the highest-impact
        // method on this controller: it both creates new courses and
        // reassigns existing ones (line below sets instructor_id from
        // $request->instructor on edit_mode). Gate immediately.
        checkAdminHasPermissionAndThrowException('course.management');
        if ($request->edit_mode == 1) {
            $course = Course::findOrFail($request->id);
            $course->instructor_id = $request->instructor;
        } else {
            $course = new Course();
            $slug = generateUniqueSlug(Course::class, $request->title);
            $course->slug = $slug;
        }

        $course->title = $request->title;
        $course->seo_description = $request->seo_description;
        $course->thumbnail = $request->thumbnail;
        $course->banner_image = $request->banner_image;
        $course->demo_video_storage = $request->demo_video_storage;
        $course->demo_video_source = $request->demo_video_storage == 'upload' ? $request->upload_path : $request->external_path;
        $course->price = $request->price;
        $course->discount = $request->discount_price;
        $course->description = $request->description;
        $course->instructor_id = $request->instructor;
        $course->save();

        // save course id in session
        Session::put('course_create', $course->id);

        return response()->json([
            'status'   => 'success',
            'message'  => __('Updated successfully'),
            'redirect' => route('admin.courses.edit', ['id' => $course->id, 'step' => $request->next_step]),
        ]);
    }

    public function edit(Request $request) {
        // FT-IDOR-27 fix — see index().
        checkAdminHasPermissionAndThrowException('course.management');
        if (!Session::get('course_create')) {
            return redirect(route('admin.courses.create'));
        }

        switch (request('step')) {
        case '1':
            $course = Course::findOrFail($request->id);
            $courseId = $course->id;
            $instructors = User::where('role', 'instructor')->get();
            $editMode = true;
            return view('course::course.create', compact('course', 'editMode', 'instructors'));
        case '2':
            $categories = CourseCategory::where('parent_id', null)->where('status', 1)->get();
            // echo "<pre>",print_r($categories);
            // exit;
            
            $course = Course::findOrFail($request->id);
            $courseId  = $course->id;
            $levels    = CourseLevel::with(['translation'])->where('status', 1)->get();
            $category  = CourseCategory::find($course->category_id);
            $subcategory = CourseCategory::where('parent_id',$course->category_id)->get();
            $languages = CourseLanguage::where('status', 1)->get();
            return view('course::course.more-information', compact(
                'categories',
                'courseId',
                'course',
                'levels',
                'category',
                'subcategory',
                'languages'
            ));
        case '3':
            $chapters = CourseChapter::with(['chapterItems'])->where(['course_id' => $request->id, 'status' => 'active'])->orderBy('order')->get();
            return view('course::course.course-content', compact('chapters'));
        case '4':
            $course = Course::findOrFail($request->id);

            $year = $request->input('year', Carbon::now()->year);

            // Get start and end of the year
            $start = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $end = $start->copy()->endOfYear();

            $orderItems = OrderItem::where('course_id', $course->id)->whereHas('order', function ($q) {
                $q->where('payment_status', 'paid');
            })->selectRaw('YEAR(created_at) as year,
                MONTH(created_at) as month,
                SUM(price) as total_price,
                AVG(commission_rate) as commission_rate')->whereBetween('created_at', [$start, $end])->groupBy('year', 'month')->orderBy('month', 'asc')->get();

            $monthlySales = array_fill(1, 12, 0);
            $commissionMonthly = array_fill(1, 12, 0);
            $netMonthly = array_fill(1, 12, 0);

            foreach ($orderItems as $item) {
                $totalAmount = $item->total_price;
                $commissionAmount = $totalAmount * ($item->commission_rate / 100);
                $netEarnings = $totalAmount - $commissionAmount;
                $monthlySales[$item->month] = $totalAmount;
                $commissionMonthly[$item->month] = $commissionAmount;
                $netMonthly[$item->month] = $netEarnings;
            }

            $oldestYear = Carbon::parse(OrderItem::orderBy('created_at', 'asc')->first()?->created_at)->year ?? Carbon::now()->year;
            $latestYear = Carbon::parse(OrderItem::orderBy('created_at', 'desc')->first()?->created_at)->year ?? Carbon::now()->year;
            $month_labels = array_map(fn($month) => Carbon::create()->month($month)->format('M'), array_keys($monthlySales));

            $commission_monthly_data = array_values($commissionMonthly);
            $net_monthly_data = array_values($netMonthly);
            $order_monthly_data = array_values($monthlySales);

            $total_chapter_items = $course->chapterItems()->count();
            $enrollments = $course->enrollments;
            $progress_ranges = array_fill_keys(['0-10', '10-20', '20-30', '30-40', '40-50', '50-60', '60-70', '70-80', '80-90', '90-100'], 0);

            foreach ($enrollments as $enrollment) {
                $percentage = min(($total_chapter_items > 0 ? $course->progresses()->where('user_id', $enrollment->user_id)->count() / $total_chapter_items * 100 : 0), 100);

                $range = $percentage == 100 ? '90-100' : floor($percentage / 10) * 10 . '-' . (floor($percentage / 10) * 10 + 10);
                $progress_ranges[$range]++;
            }
            $total_enrollments = $enrollments->count();

            return view('course::course.analytics', compact('course', 'progress_ranges', 'total_enrollments', 'oldestYear', 'latestYear', 'month_labels', 'commission_monthly_data', 'net_monthly_data', 'order_monthly_data'));
        case '5':
            $courseId = request('id');
            $course = Course::findOrFail($courseId);
            return view('course::course.finish', compact('course'));
        default:
            break;
        }
    }

    function update(Request $request) {
        // FT-IDOR-27 fix — see index(). update() drives steps 2-5 of
        // the course-builder including the publish toggle on step 5.
        checkAdminHasPermissionAndThrowException('course.management');
        switch ($request->step) {
        case '2':
            $request->validate([
                'course_duration' => ['required', 'numeric', 'min:0'],
                'category'        => ['required'],
                'sub_category'        => ['required'],
            ]);
            $this->storeMoreInfo($request);
            return response()->json([
                'status'   => 'success',
                'message'  => __('Updated Successfully'),
                'redirect' => route('admin.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
            ]);
            break;
        case '3':
            return response()->json([
                'status'   => 'success',
                'message'  => __('Updated successfully'),
                'redirect' => route('admin.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
            ]);
            break;
        case '4':
            return response()->json([
                'status'   => 'success',
                'message'  => __('Updated successfully'),
                'redirect' => route('admin.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
            ]);
            break;
        case '5':
            $request->validate([
                'status'               => ['required'],
                'message_for_reviewer' => ['nullable', 'max:1000'],
            ]);
            $this->storeFinish($request);
            return response()->json([
                'status'   => 'success',
                'message'  => __('Updated Successfully'),
                'redirect' => $request->next_step == 5 ? route('admin.courses.index') : route('admin.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
            ]);

        default:
            # code...
            break;
        }
    }

    function storeMoreInfo(Request $request) {
        checkAdminHasPermissionAndThrowException('course.management');
        $course = Course::findOrFail($request->course_id);
        $course->capacity = $request->capacity;
        $course->duration = $request->course_duration;
        $course->category_id = $request->category;
        $course->sub_category_id = $request->sub_category;
        $course->qna = $request->qna;
        $course->downloadable = $request->downloadable;
        $course->certificate = $request->certificate;
        $course->partner_instructor = $request->partner_instructor;
        $course->save();

        // delete unselected partner instructor
        CoursePartnerInstructor::where('course_id', $course->id)
            ->whereNotIn('instructor_id', $request->partner_instructors ?? [])->delete();

        // insert partner instructor
        foreach ($request->partner_instructors ?? [] as $instructor) {
            CoursePartnerInstructor::updateOrCreate(
                ['course_id' => $course->id, 'instructor_id' => $instructor],
            );
        }

        // insert levels
        CourseSelectedLevel::where('course_id', $course->id)
            ->whereNotIn('level_id', $request->levels ?? [])->delete();

        foreach ($request->levels ?? [] as $level) {
            CourseSelectedLevel::updateOrCreate(
                ['course_id' => $course->id, 'level_id' => $level],
            );
        }

        //insert languages
        CourseSelectedLanguage::where('course_id', $course->id)
            ->whereNotIn('language_id', $request->languages ?? [])->delete();

        foreach ($request->languages ?? [] as $language) {
            CourseSelectedLanguage::updateOrCreate(
                ['course_id' => $course->id, 'language_id' => $language],
            );
        }
    }

    function storeFinish(Request $request) {
        checkAdminHasPermissionAndThrowException('course.management');
        $course = Course::findOrFail($request->course_id);
        $course->message_for_reviewer = $request->message_for_reviewer;
        $course->status = $request->status;
        // $course->is_approved = 'approved';
        $course->save();
    }

    function getInstructors(Request $request) {
        // FT-IDOR-27 fix — see index(). getInstructors leaks every
        // instructor's name/email via search; gate on the same slug.
        checkAdminHasPermissionAndThrowException('course.management');
        $instructors = User::where('role', 'instructor')
            ->where(function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->q . '%')
                    ->orWhere('email', 'like', '%' . $request->q . '%');
            })
            ->where('id', '!=', auth()->id())
            ->get();
        return response()->json($instructors);
    }

    function statusUpdate(Request $request, string $id) {
        // FT-IDOR-27 fix — see index(). statusUpdate flips
        // is_approved which gates whether the course is visible to
        // the public catalogue. A sub-admin without course.management
        // could mass-approve every pending course (e.g. malicious
        // content awaiting moderation), or mass-reject competitor
        // courses. Tighten now.
        // FT-VAL-X — was no `status` validation either; status came
        // straight from request and was assigned to is_approved.
        // Whitelist the valid enum values.
        checkAdminHasPermissionAndThrowException('course.management');
        $request->validate([
            'status' => ['required', 'in:approved,pending,rejected'],
        ]);
        $course = Course::findOrFail($id);
        $previous = $course->is_approved;
        $course->is_approved = $request->status;
        $course->save();

        // Notify the course's coach when admin flips the approval state.
        if ($previous !== $course->is_approved) {
            try {
                $coachId = $course->instructor_id ?: $course->added_by;
                $coach = $coachId ? \App\Models\User::find($coachId) : null;
                if ($coach) {
                    if ($course->is_approved === 'approved') {
                        $coach->notify(new \App\Notifications\CourseApprovedToCoach($course));
                    } else {
                        $coach->notify(new \App\Notifications\CourseRejectedToCoach($course, (string) $course->is_approved));
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Notify coach of course approval change failed: ' . $e->getMessage());
            }
        }

        return response(['status' => 'success', 'message' => __('Updated successfully')]);
    }

    function destroy(string $id) {
        checkAdminHasPermissionAndThrowException('course.management');
        $course = Course::findOrFail($id);
        if ($course->enrollments()->count() > 0) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('The course cannot be deleted because it has enrollments.')]);
        }
        $course->delete();

        return response()->json(['status' => 'success', 'message' => __('Course deleted successfully')]);
    }
}
