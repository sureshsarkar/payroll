<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QnaReplyResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id'          => (int) $this->id,
            'question_id' => (int) $this->question_id,
            'reply'       => (string) $this->reply,
            'created_at'   => (string) formattedDateTime($this->created_at),
            'user'          => new InstructorResource($this->user),
        ];
    }
}
