<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherBatch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'institute_id',
        'session_id',
        'billing_month',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'session_id' => 'integer',
            'total_amount' => 'float',
            'paid_amount' => 'float',
            'remaining_amount' => 'float',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(FeeVoucher::class, 'batch_id', 'id');
    }
}
