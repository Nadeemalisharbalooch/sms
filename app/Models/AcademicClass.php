<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'institute_id',
        'name',
        'code',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'institute_id' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(AcademicSection::class, 'class_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }
}