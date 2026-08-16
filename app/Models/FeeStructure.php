<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    protected $fillable = [
        'institute_id',
        'session_id',
        'class_id',
        'fee_category_id',
        'amount',
        'recurrence',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'session_id' => 'integer',
            'class_id' => 'integer',
            'fee_category_id' => 'integer',
            'amount' => 'float',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }
}
