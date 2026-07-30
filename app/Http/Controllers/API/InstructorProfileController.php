<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public-facing instructor profile for the mobile app.
 *
 * Mirrors the web `coach-details/{id}` page (HomePageController::
 * instructorDetails) but returns JSON. Used by the tap-instructor flow
 * on the course-detail and cart screens.
 *
 * Route:  GET /api/instructor/{id}
 *
 * Auth:   not required — instructor profiles are public-facing, same
 *         as the web side. We don't expose private fields (email,
 *         phone, address, etc.) to avoid PII leakage.
 */
class InstructorProfileController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        // `is_banned` is stored as varchar "no"/"yes" (legacy column type),
        // so we filter by value rather than numeric 0.
        $instructor = User::where('id', $id)
            ->where('status', 'active')
            ->where('is_banned', '!=', 'yes')
            ->whereIn('role', ['instructor', 'admin'])
            ->first();

        if (!$instructor) {
            return response()->json([
                'status' => 'error', 'message' => 'Instructor not found',
            ], 404);
        }

        // Active courses + per-course average rating + enrollment count.
        $courses = Course::active()
            ->where('instructor_id', $id)
            ->withCount(['reviews as average_rating' => function ($q) {
                $q->select(DB::raw('coalesce(avg(rating), 0)'))->where('status', 1);
            }])
            ->withCount(['enrollments as enrolled_count'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // user_experiences + user_education are read via DB facade to
        // avoid coupling the API to model layer that mis-pluralises the
        // education table name. Schema is fixed in legacy migrations.
        $experiences = DB::table('user_experiences')
            ->where('user_id', $id)
            ->orderByDesc('id')
            ->get(['id', 'company', 'position', 'start_date', 'end_date', 'current']);

        $educations = DB::table('user_education')
            ->where('user_id', $id)
            ->orderByDesc('id')
            ->get(['id', 'organization', 'degree', 'start_date', 'end_date', 'current']);

        $overallAvg = $courses->avg('average_rating') ?: 0.0;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'             => $instructor->id,
                'name'           => $instructor->name,
                'role'           => $instructor->role,
                'image'          => $instructor->image,
                'short_bio'      => $instructor->short_bio,
                'bio'            => $instructor->bio,
                'course_count'   => $courses->count(),
                'overall_rating' => round((float) $overallAvg, 2),
                'courses'        => $courses->map(fn ($c) => [
                    'id'             => $c->id,
                    'slug'           => $c->slug,
                    'title'          => $c->title,
                    'thumbnail'      => $c->thumbnail,
                    'price'          => (float) $c->price,
                    'discount'       => (float) ($c->discount ?? 0),
                    'average_rating' => round((float) $c->average_rating, 1),
                    'enrolled_count' => (int) $c->enrolled_count,
                ])->values(),
                'experiences'    => $experiences->map(fn ($e) => [
                    'id'         => $e->id,
                    'company'    => $e->company,
                    'position'   => $e->position,
                    'start_date' => $e->start_date,
                    'end_date'   => $e->end_date,
                    'current'    => (bool) $e->current,
                ])->values(),
                'educations'     => $educations->map(fn ($ed) => [
                    'id'           => $ed->id,
                    'organization' => $ed->organization,
                    'degree'       => $ed->degree,
                    'start_date'   => $ed->start_date,
                    'end_date'     => $ed->end_date,
                    'current'      => (bool) $ed->current,
                ])->values(),
            ],
        ]);
    }
}
