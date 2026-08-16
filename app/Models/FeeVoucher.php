<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeVoucher extends Model
{
    protected $fillable = [
        'institute_id',
        'session_id',
        'student_id',
        'billing_month',
        'due_date',
        'total_amount',
        'paid_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'session_id' => 'integer',
            'student_id' => 'integer',
            'due_date' => 'date',
            'total_amount' => 'float',
            'paid_amount' => 'float',
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

    public function items(): HasMany
    {
        return $this->hasMany(FeeVoucherItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function getBalanceDueAttribute(): float
    {
        return round($this->total_amount - $this->paid_amount, 2);
    }
}
