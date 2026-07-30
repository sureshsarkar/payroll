<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptsResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id'           => (int) $this->id,
            'course_title' => (string) $this->quiz->course->title,
            'quiz'         => (string) $this->quiz->title,
            'user_grade'   => (int) $this->user_grade,
            'status'       => (string) $this->status,
            'created_at'   => (string) formatDate($this->created_at),
            'results'       => new QuizResultResource($this->result),
        ];
    }
}
