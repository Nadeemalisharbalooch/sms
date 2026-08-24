<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableTimeSlot extends Model
{
    protected $fillable = [
        'institute_id',
        'name',
        'start_time',
        'end_time',
        'is_break',
        'sort_order',
        'is_active',
        'days',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'is_break' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'days' => 'array',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'time_slot_id');
    }
}
