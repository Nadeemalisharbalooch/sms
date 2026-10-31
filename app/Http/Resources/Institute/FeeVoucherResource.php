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
            'batch_id' => $this->batch_id,
            'billing_month' => $this->billing_month,
            'total_amount' => $this->total_amount,
            'late_fee_amount' => $this->late_fee_amount ?? 0,
            'late_fee_waived' => (bool) ($this->late_fee_waived ?? false),
            'paid_amount' => $this->paid_amount,
            'balance_due' => $this->balance_due,
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
            'items' => $this->items
                ->groupBy(fn ($item) => $item->description.'|'.($item->type ?? 'charge'))
                ->map(fn ($items) => [
                    'description' => $items->first()->description,
                    'amount' => round((float) $items->sum('amount'), 2),
                    'type' => $items->first()->type ?? 'charge',
                ])
                ->values(),
        ];
    }
}
