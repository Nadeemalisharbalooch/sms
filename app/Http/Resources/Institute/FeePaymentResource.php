<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fee_voucher_id' => $this->fee_voucher_id,
            'amount_paid' => $this->amount_paid,
            'payment_date' => $this->payment_date?->toDateString(),
            'payment_method' => $this->payment_method,
            'collected_by_user_id' => $this->collected_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
