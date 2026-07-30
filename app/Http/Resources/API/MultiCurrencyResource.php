<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MultiCurrencyResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'currency_icon'     => (bool) $this->currency_icon,
            'currency_name'     => (string) $this->currency_name,
            'currency_code'     => (string) $this->currency_code,
            'country_code'      => (string) $this->country_code,
            'currency_rate'     => (float) $this->currency_rate,
            'currency_position' => (string) $this->currency_position,
            'is_default'        => (string) $this->is_default,
            'status'            => (string) $this->status,
        ];
    }
}
