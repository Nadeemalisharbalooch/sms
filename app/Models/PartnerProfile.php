<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerProfile extends Model
{
    protected $fillable = ['user_id', 'company_name', 'phone', 'website_url', 'promotion_methods', 'tax_id_or_cnic', 'referral_code', 'status', 'commission_rate', 'payout_method', 'payout_details', 'terms_agreed_at'];
    protected function casts(): array { return ['promotion_methods' => 'array', 'tax_id_or_cnic' => 'encrypted', 'payout_details' => 'encrypted:array', 'terms_agreed_at' => 'datetime', 'commission_rate' => 'decimal:2']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function commissions(): HasMany { return $this->hasMany(PartnerCommission::class); }
    public function withdrawals(): HasMany { return $this->hasMany(PartnerWithdrawal::class); }
}
