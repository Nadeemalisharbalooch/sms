<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'voucher_id' => $this->id,
            'student_id' => $this->student_id,
            'session_id' => $this->session_id,
            'billing_month' => $this->billing_month,
            'due_date' => $this->due_date?->toDateString(),
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_due' => round($this->total_amount - $this->paid_amount, 2),
            'status' => $this->status,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'fee_name' => $item->fee_name,
                'amount' => $item->amount,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
