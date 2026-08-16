<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institute_id' => $this->institute_id,
            'session_id' => $this->session_id,
            'class_id' => $this->class_id,
            'fee_category_id' => $this->fee_category_id,
            'fee_category_name' => $this->whenLoaded('feeCategory', fn () => $this->feeCategory->name),
            'amount' => $this->amount,
            'recurrence' => $this->recurrence,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
