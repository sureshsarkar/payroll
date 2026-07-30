<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizQuestionResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id'    => (int) $this->id,
            'title' => (string) $this->title,
            'type' => (string) $this->type,
            'answers' => QuizQuestionAnswerResource::collection($this->answers),
        ];
    }
}
