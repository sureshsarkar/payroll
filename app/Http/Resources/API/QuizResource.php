<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        $data = [
            'id'    => (int) $this->id,
            'title' => (string) $this->title,
        ];
        if ($request->routeIs('api.get-file-info')) {
            $data['time'] = (int) $this->time;
            $data['attempt'] = (int) $this->attempt;
            $data['pass_mark'] = (int) $this->pass_mark;
            $data['total_mark'] = (int) $this->total_mark;
            $data['total_questions'] = (int) $this->questions_count;
        }

        if ($request->routeIs('api.quiz-index')) {
            $data['time'] = (int) $this->time;
            $data['attempt'] = (int) $this->attempt;
            $data['pass_mark'] = (int) $this->pass_mark;
            $data['total_mark'] = (int) $this->total_mark;
            $data['total_questions'] = (int) $this->questions_count;
            $data['questions'] = QuizQuestionResource::collection($this->questions);
        }
        return $data;
    }
}
