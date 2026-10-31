<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentOneTimeFee extends Model
{
    protected $fillable = ['institute_id', 'session_id', 'student_id', 'fee_category_id', 'amount', 'is_billed'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'is_billed' => 'boolean'];
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }
}
