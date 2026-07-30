<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\CourseListResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CartController extends Controller {
    public function index(): JsonResponse {
        $user = auth()->user();
        $currency = strtoupper(request()->query('currency'));
        $data = [
            'total_qty'    => (int) $user->cartCount,
            'total_amount' => (string) apiCurrency($user->cartTotal, $currency),
        ];
        if ($user->cartCount > 0) {
            $cart_courses = Course::select('id', 'slug', 'title', 'instructor_id', 'thumbnail', 'price', 'discount')->active()->with(['instructor:id,name,image'])
                ->whereHas('carts', fn($q) => $q->where('user_id', $user->id))->withCount(['reviews as average_rating' => fn($q) => $q->select(DB::raw('coalesce(avg(rating), 0)'))->where('status', 1), 'enrollments'])->get();

            $data['cart_courses'] = CourseListResource::collection($cart_courses);
        }
        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    public function add_to_cart(string $slug): JsonResponse {
        $course = Course::select('id', 'instructor_id')->whereSlug($slug)->first();
        $user = auth()->user();
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        if ($course->instructor_id == $user->id) {
            return response()->json(['status' => 'error', 'message' => 'You can not add to cart your own course!'], 400);
        }
        if ($user->enrollments()->select('course_id')->where('course_id', $course->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Already purchased'], 400);
        }
        if ($user->carts()->select('course_id')->where('course_id', $course->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Already added to cart!'], 400);
        }
        $user->carts()->create(['course_id' => $course->id]);

        return response()->json(['status' => 'success', 'message' => 'Added to cart successfully!', 'cart_count' => $user->cartCount], 200);
    }

    public function remove_from_cart(string $slug): JsonResponse {
        $user = auth()->user();

        $course = Course::select('id')->whereSlug($slug)->first();
        if (!$course || !$user->carts()->where('course_id', $course->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        $user->carts()->where('course_id', $course->id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Item removed from cart!'], 200);
    }
}
