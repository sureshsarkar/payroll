<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        $subTotal = $this->calculateSubTotal();
        $discount = $this->coupon_discount_amount ?? 0;
        $gatewayCharge = $this->gateway_charge ?? 0;
        $total = ($subTotal - $discount + $gatewayCharge) * ($this->conversion_rate ?? 1);
        $sub_total_conversion = $subTotal * ($this->conversion_rate ?? 1);

        $data = [
            'invoice_id'     => (string) $this->invoice_id,
            'payment_method' => (string) $this->payment_method,
            'paid_amount'    => $this->formatCurrency($this->paid_amount, $this->payable_currency),
            'payment_status' => (string) $this->payment_status,
            'status'         => (string) $this->status,
            'created_at'     => (string) formatDate($this->created_at),
            'billed_to'      => [
                'name'    => (string) $this->user->name,
                'email'   => (string) $this->user->email,
                'address' => (string) $this->user->address,
                'phone'   => (string) $this->user->phone,
            ],
            'order_items'    => $this->transformOrderItems(),
            'summary'        => [
                'sub_total'      => $this->formatCurrency($sub_total_conversion, $this->payable_currency),
                'discount'       => $this->formatCurrency($discount, $this->payable_currency),
                'gateway_charge' => $this->formatCurrency($gatewayCharge, $this->payable_currency),
                'total'          => $this->formatCurrency($total, $this->payable_currency),
            ],
        ];
        return $data;
    }
    /**
     * Calculate the subtotal.
     */
    private function calculateSubTotal(): float {
        return $this->orderItems->sum('price');
    }
    /**
     * Format currency with value and currency code.
     */
    private function formatCurrency($value, $currency) {
        return (string) "{$value} {$currency}";
    }
    /**
     * Transform order items into an array.
     */
    private function transformOrderItems() {
        return $this->orderItems->map(function ($item) {
            $price = $item->price * ($this->conversion_rate ?? 1);
            return [
                'price'        => $this->formatCurrency($price, $this->payable_currency),
                'course_title' => (string) optional($item->course)->title ?? 'N/A',
                'instructor'   => (string) optional($item->course->instructor)->name ?? 'N/A',
            ];
        })->toArray();
    }
}
