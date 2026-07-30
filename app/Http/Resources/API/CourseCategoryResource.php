<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCategoryResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        if($request->is('api/course-main-categories')){
            return [
                'slug'             => (string) $this->slug,
                'name'             => (string) $this->translations->first()->name,
                'icon'             => (string) $this->icon,
                'show_at_trending' => (bool) $this->show_at_trending,
            ]; 
        }
        return [
            'slug'             => (string) $this->slug,
            'name'             => (string) $this->translations->first()->name
        ];
    }
}
