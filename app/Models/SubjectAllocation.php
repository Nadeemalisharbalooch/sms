<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectAllocation extends Model
{
    protected $table = 'subject_allocations';

    protected $fillable = [
        'session_id',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_user_id',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'class_id' => 'integer',
            'section_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_user_id' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }
}