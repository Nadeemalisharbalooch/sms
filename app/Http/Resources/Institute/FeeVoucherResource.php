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
            'billing_month' => $this->billing_month,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_due' => round($this->total_amount - $this->paid_amount, 2),
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'items' => $this->items->map(fn ($item) => [
                'fee_name' => $item->fee_name,
                'amount' => $item->amount,
            ])->values(),
        ];
    }
}
