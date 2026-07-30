<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomPageResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'slug'    => (string) $this->slug,
            'name'    => (string) $this->translations?->first()?->name,
            'content' => (string) $this->translations?->first()?->content,
        ];
    }
}
