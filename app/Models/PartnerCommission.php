<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerCommission extends Model
{
    protected $fillable = ['partner_profile_id', 'institute_id', 'subscription_invoice_id', 'amount', 'status', 'clears_at'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'clears_at' => 'datetime']; }
    public function partner(): BelongsTo { return $this->belongsTo(PartnerProfile::class, 'partner_profile_id'); }
    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
}
