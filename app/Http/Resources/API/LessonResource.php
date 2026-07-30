<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        if ($request->routeIs('api.get-file-info') || $request->routeIs('api.free-lesson')) {
            return [
                'id'              => (int) $this->id,
                'title'           => (string) $this->title,
                'description'     => (string) $this->description,
                'file_path'       => (string) generateVideoEmbedUrl($this->file_path, $this->storage, $this->file_type),
                'storage'         => (string) $this->storage,
                'file_type'       => (string) $this->file_type,
                'duration'        => (string) convertMinutesToHoursAndMinutes($this->duration),
                'is_downloadable' => (bool) $this->downloadable,
            ];
        }
        // F5 (audit 2026-06-26) — this branch feeds the PUBLIC course-detail
        // listing (no auth). It must NOT leak a playable URL for PAID lessons.
        // Only free lessons expose file_path; for paid lessons it is blanked +
        // marked locked. Enrolled students still get the real URL through
        // api.get-file-info (the branch above), which enforces enrollment.
        $isFree = (bool) $this->is_free;
        return [
            'id'        => (int) $this->id,
            'title'     => (string) $this->title,
            'file_type' => (string) $this->file_type,
            'file_path' => $isFree ? (string) generateVideoEmbedUrl($this->file_path, $this->storage, $this->file_type) : '',
            'is_locked' => ! $isFree,
            'duration'  => (string) convertMinutesToHoursAndMinutes($this->duration),
            'is_free'   => $isFree,
        ];
    }
}




