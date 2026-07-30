<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        // Audit L5 — don't expose live-join credentials for a class that has
        // already ENDED, so stale meeting links don't linger in app caches/logs.
        // Fails OPEN (keeps the credentials) if end_time can't be parsed, so an
        // upcoming/live class's "Join" button is never accidentally broken.
        $ended = false;
        try {
            $end = $this->end_time ? \Illuminate\Support\Carbon::parse($this->end_time) : null;
            $ended = $end && $end->isPast();
        } catch (\Throwable $e) {
            $ended = false;
        }

        return [
            'id'          => (int) $this->id,
            'title'       => (string) $this->title,
            'description' => (string) $this->description,
            'start_time'  => (string) $this->start_time,
            'end_time'    => (string) $this->end_time,
            'duration'    => (string) convertMinutesToHoursAndMinutes($this->duration),
            'is_live_now' => (string) $this->is_live_now,
            'type'        => (string) $this->live->type,
            'meeting_id'  => $ended ? '' : (string) $this->live->meeting_id,
            'join_url'    => $ended ? '' : (string) $this->live->join_url,
        ];
    }
}
