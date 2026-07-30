<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewsResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id'           => (int) $this->id,
            'course_title' => (string) $this->course->title,
            'rating'       => (int) $this->rating,
            'review'       => (string) $this->review,
            'created_at'   => (string) formatDate($this->created_at),
            'status'       => (bool) $this->status,
        ];
    }
}
