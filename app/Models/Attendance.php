<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'session_id', 'class_id', 'section_id', 'subject_id', 'student_id',
        'date', 'status', 'marked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer', 'class_id' => 'integer', 'section_id' => 'integer',
            'subject_id' => 'integer', 'student_id' => 'integer', 'marked_by_user_id' => 'integer',
            'date' => 'date',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }
}
