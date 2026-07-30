<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnBoardingScreenResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'title'       => (string) data_get($this, 'title'),
            'description' => (string) data_get($this, 'description'),
        ];
    }
}
