<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeVoucherItem extends Model
{
    protected $fillable = [
        'fee_voucher_id',
        'fee_name',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'fee_voucher_id' => 'integer',
            'amount' => 'float',
        ];
    }

    public function feeVoucher(): BelongsTo
    {
        return $this->belongsTo(FeeVoucher::class);
    }
}
