<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;

class FavoriteController extends Controller {
    public function update(Course $course) {
        if (!$course) {
            return response()->json(['status' => 'not-found', 'message' => __('Not Found!')]);
        }
        $userFavorites = userAuth()->favoriteCourses();

        if ($userFavorites->where('course_id', $course->id)->exists()) {
            $userFavorites->detach($course);
            return response()->json(['status' => 'remove', 'message' => __('Removed from wishlist')]);
        } else {
            $userFavorites->attach($course);
            return response()->json(['status' => 'added', 'message' => __('Added to wishlist')]);
        }
    }

    public function destroy($slug) {
        $course = Course::whereSlug($slug)->first();
        if (!$course) {
            return response()->json(['status' => 'not-found', 'message' => __('Not Found!')]);
        }
        userAuth()->favoriteCourses()->detach($course);
        $content = view('frontend.wishlist.wishlist-card')->render();
        return response()->json(['status' => 'success', 'content' => $content, 'message' => __('Removed from wishlist')]);
    }
}
