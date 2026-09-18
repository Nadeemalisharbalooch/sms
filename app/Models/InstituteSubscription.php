<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstituteSubscription extends Model
{
    protected $fillable = [
        'institute_id', 'plan_id', 'status', 'blocked', 'starts_at', 'ends_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'blocked' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(SubscriptionInvoice::class, 'subscription_id')->latestOfMany();
    }
}
