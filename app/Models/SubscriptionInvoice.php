<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'institute_id', 'subscription_id', 'plan_id', 'invoice_number', 'amount', 'currency',
        'billing_interval', 'due_date', 'status', 'payment_method', 'payment_reference',
        'payment_screenshot', 'payment_submitted_at', 'paid_at', 'verified_by_user_id', 'notes',
    ];

    protected $appends = ['payment_screenshot_url'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'payment_submitted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(InstituteSubscription::class, 'subscription_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function getPaymentScreenshotUrlAttribute(): ?string
    {
        return $this->payment_screenshot
            ? Storage::disk('public')->url($this->payment_screenshot)
            : null;
    }
}
