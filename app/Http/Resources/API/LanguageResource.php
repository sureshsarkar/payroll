<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LanguageResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'code'       => (string) $this->code,
            'name'       => (string) $this->name,
            'direction'  => (string) $this->direction,
            'is_default' => (bool) $this->is_default,
            'status'     => (bool) $this->status,
        ];
    }
}
