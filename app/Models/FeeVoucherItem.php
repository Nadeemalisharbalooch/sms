<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeVoucherItem extends Model
{
    protected $fillable = [
        'voucher_id',
        'description',
        'amount',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'voucher_id' => 'integer',
            'amount' => 'float',
        ];
    }

    public function feeVoucher(): BelongsTo
    {
        return $this->belongsTo(FeeVoucher::class, 'voucher_id');
    }
}
