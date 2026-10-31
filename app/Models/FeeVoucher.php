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
        'batch_id',
        'billing_month',
        'due_date',
        'total_amount',
        'late_fee_amount',
        'late_fee_waived',
        'carried_forward_amount',
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
            'late_fee_amount' => 'float',
            'late_fee_waived' => 'boolean',
            'carried_forward_amount' => 'float',
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
        return $this->hasMany(FeeVoucherItem::class, 'voucher_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function getBalanceDueAttribute(): float
    {
        $lateFee = ! $this->late_fee_waived && now()->toDateString() > $this->due_date?->toDateString()
            && (float) $this->paid_amount < (float) $this->total_amount
            ? (float) ($this->late_fee_amount ?? 0)
            : 0;

        return round(max(0, $this->total_amount + $lateFee - $this->paid_amount - (float) ($this->carried_forward_amount ?? 0)), 2);
    }
}
