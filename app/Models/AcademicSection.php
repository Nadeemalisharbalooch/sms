<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSection extends Model
{
    protected $table = 'sections';

    protected $fillable = [
        'class_id',
        'name',
        'code',
        'capacity',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function sectionTeachers(): HasMany
    {
        return $this->hasMany(SectionTeacher::class, 'section_id');
    }
}
