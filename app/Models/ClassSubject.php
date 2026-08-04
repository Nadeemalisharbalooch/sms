<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSubject extends Model
{
    protected $table = 'class_subjects';

    protected $fillable = [
        'class_id',
        'section_id',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'section_id' => 'integer',
            'subject_id' => 'integer',
        ];
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AcademicSection::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}