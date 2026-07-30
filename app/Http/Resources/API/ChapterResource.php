<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use App\Http\Resources\API\ChapterItemResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ChapterResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id'        => (int) $this->id,
            'title'        => (string) $this->title,
            'chapters' => ChapterItemResource::collection($this->chapterItems),
        ];
    }
}
