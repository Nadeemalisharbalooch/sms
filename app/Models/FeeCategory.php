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
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
