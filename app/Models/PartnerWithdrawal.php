<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWithdrawal extends Model
{
    protected $fillable = ['partner_profile_id', 'amount', 'status', 'transaction_reference', 'processed_by_user_id', 'processed_at'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'processed_at' => 'datetime']; }
    public function partner(): BelongsTo { return $this->belongsTo(PartnerProfile::class, 'partner_profile_id'); }
}
