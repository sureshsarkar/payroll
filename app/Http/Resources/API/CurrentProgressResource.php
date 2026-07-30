<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentProgressResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'type'       => (string) $this->type,
            'chapter_id' => (int) $this->chapter_id,
            'lesson_id'  => (int) $this->lesson_id,
            'watched'    => (int) $this->watched,
            'current'    => (int) $this->current,
        ];
    }
}
