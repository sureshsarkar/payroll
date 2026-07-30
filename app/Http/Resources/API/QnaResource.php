<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QnaResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        if($request->routeIs('api.questions-create')){
            return [
                'id'            => (int) $this->id,
                'question'      => (string) $this->question_title,
                'description'   => (string) $this->question_description,
                'seen'          => (bool) $this->seen,
                'created_at'   => (string) formattedDateTime($this->created_at),
                'user'          => new InstructorResource($this->user),
            ];
        }
        return [
            'id'            => (int) $this->id,
            'question'      => (string) $this->question_title,
            'description'   => (string) $this->question_description,
            'replies_count' => (int) $this->replies_count,
            'seen'          => (bool) $this->seen,
            'created_at'   => (string) formattedDateTime($this->created_at),
            'user'          => new InstructorResource($this->user),
            'replies'       => QnaReplyResource::collection($this->replies),
        ];
    }
}
