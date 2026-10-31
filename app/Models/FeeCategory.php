<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeCategory extends Model
{
    protected $fillable = [
        'institute_id',
        'name',
        'description',
        'type',
        'default_months',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'default_months' => 'array',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
