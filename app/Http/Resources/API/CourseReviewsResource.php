<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseReviewsResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'rating' => (float) $this->rating,
            'review' => (string) $this->review,
            'name'   => (string) $this->user->name,
            'avatar' => (string) $this->user->image,
        ];
    }
}
