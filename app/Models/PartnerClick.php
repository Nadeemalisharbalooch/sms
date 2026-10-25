<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerClick extends Model
{
    public $timestamps = false;
    protected $fillable = ['partner_profile_id', 'ip_hash', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
}
