<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSetting extends Model
{
    protected $fillable = ['institute_id', 'late_fee_type', 'late_fee_value'];

    protected function casts(): array
    {
        return ['late_fee_value' => 'float'];
    }
}
