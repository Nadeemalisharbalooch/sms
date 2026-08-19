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
            'student' => $this->whenLoaded('student', function () {
                $enrollment = $this->student->enrollments->first();

                return [
                    'id' => $this->student->id,
                    'name' => trim($this->student->first_name.' '.$this->student->last_name),
                    'class' => $enrollment?->academicClass?->name ?? 'N/A',
                ];
            }),
            // Group on read too, so older vouchers that already contain
            // duplicate names are returned as a single fee item.
            'items' => $this->items
                ->groupBy('fee_name')
                ->map(fn ($items, $feeName) => [
                    'fee_name' => $feeName,
                    'amount' => round((float) $items->sum('amount'), 2),
                ])
                ->values(),
        ];
    }
}
