<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'invoice_id'     => (string) $this->invoice_id,
            'payment_method' => (string) $this->payment_method,
            'paid_amount'    => (string) "{$this->paid_amount} {$this->payable_currency}",
            'payment_status' => (string) $this->payment_status,
            'status'         => (string) $this->status,
        ];
    }
}
