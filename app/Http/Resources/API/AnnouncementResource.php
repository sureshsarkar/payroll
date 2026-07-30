<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        // Audit 2026-05-18 phase 3 — also surface is_pinned + attachments.
        return [
            'id'           => (int) $this->id,
            'title'        => (string) $this->title,
            'announcement' => (string) $this->announcement,
            'is_pinned'    => (bool) ($this->is_pinned ?? false),
            'created_at'   => (string) formatDate($this->created_at),
            'sent_at'      => $this->sent_at ? (string) formatDate($this->sent_at) : (string) formatDate($this->created_at),
            'batch'        => $this->relationLoaded('batch') && $this->batch
                ? ['id' => (int) $this->batch->id, 'title' => (string) $this->batch->title]
                : null,
            'attachments'  => $this->relationLoaded('attachments')
                ? $this->attachments->map(fn ($a) => [
                    'id'         => (int) $a->id,
                    'filename'   => (string) $a->filename,
                    'size_bytes' => (int) $a->size_bytes,
                    'mime_type'  => (string) $a->mime_type,
                    'url'        => route('instructor.announcements.attachments.download', $a->id),
                ])
                : [],
            'instructor'   => new InstructorResource($this->instructor),
        ];
    }
}
