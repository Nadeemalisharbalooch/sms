<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeAssignment extends Model
{
    protected $fillable = [
        'institute_id',
        'session_id',
        'student_id',
        'fee_category_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'session_id' => 'integer',
            'student_id' => 'integer',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }
}
