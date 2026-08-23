<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    protected $fillable = [
        'session_id',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_user_id',
        'time_slot_id',
        'day_of_week',
        'room_number',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'class_id' => 'integer',
            'section_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_user_id' => 'integer',
            'time_slot_id' => 'integer',
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
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableTimeSlot::class, 'time_slot_id');
    }
}
