<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePayment extends Model
{
    protected $fillable = [
        'institute_id',
        'fee_voucher_id',
        'amount_paid',
        'payment_date',
        'payment_method',
        'collected_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'fee_voucher_id' => 'integer',
            'amount_paid' => 'float',
            'payment_date' => 'date',
            'collected_by_user_id' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function feeVoucher(): BelongsTo
    {
        return $this->belongsTo(FeeVoucher::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }
}
