<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseListResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        $currency = strtoupper($request->query('currency'));
        return [
            'id'           => (string) $this->id,
            'slug'           => (string) $this->slug,
            'title'          => (string) $this->title,
            'thumbnail'      => (string) $this->thumbnail,
            'price'          => (string) apiCurrency($this->price, $currency),
            'discount'       => (string) apiCurrency($this->discount, $currency),
            'instructor'     => new InstructorResource($this->instructor),
            'students'       => (int) $this->enrollments_count,
            'average_rating' => (float) $this->average_rating,
        ];
    }
}
