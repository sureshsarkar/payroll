<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChapterItemResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        $data = ['type' => (string) $this->type];

        if (in_array($this->type,['lesson','document','live'])) {
            $data['item'] = $this->lesson ? new LessonResource($this->lesson) : [];
        } elseif ($this->type == 'quiz') {
            $data['item'] = $this->quiz ? new QuizResource($this->quiz) : [];
        }
        return $data;
    }
}
