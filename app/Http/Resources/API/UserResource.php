<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id'         => (int) $this->id,
            'name'       => (string) $this->name,
            'email'      => (string) $this->email,
            'phone'      => (string) $this->phone,
            'age'        => (int) $this->age,
            'image'      => (string) $this->image,
            'job_title'  => (string) $this->job_title,
            'short_bio'  => (string) $this->short_bio,
            'bio'        => (string) $this->bio,
            'gender'     => (string) $this->gender,
            'country_id' => (int) $this->country_id,
            'state'      => (string) $this->state,
            'city'       => (string) $this->city,
            'address'    => (string) $this->address,
            "facebook"   => (string) $this->facebook,
            "twitter"    => (string) $this->twitter,
            "linkedin"   => (string) $this->linkedin,
            "website"    => (string) $this->website,
            "github"     => (string) $this->github,
            // Two-factor status — the mobile app reads this to decide
            // whether to show "Enable 2FA" or "Manage 2FA" in settings.
            // Mirrors the same boolean used in
            // `User::hasTwoFactorEnabled()`: a non-null confirmed_at
            // means the user has scanned the QR + entered a valid code.
            "two_factor_enabled" => (bool) ($this->two_factor_confirmed_at !== null),
            // Role signals so the mobile app can choose the correct UI:
            //   is_coach       — a top-level coach account (role=instructor, no coach_id)
            //   is_coach_staff — a coach's staff member (coach_id set, not student/admin)
            // Without these the app fell back to the student layout for staff.
            'role'           => (string) ($this->role ?? ''),
            'coach_id'       => $this->coach_id !== null ? (int) $this->coach_id : null,
            'is_coach'       => $this->role === 'instructor' && empty($this->coach_id),
            'is_coach_staff' => ! empty($this->coach_id) && ! in_array($this->role, ['student', 'admin'], true),
        ];
    }
}
