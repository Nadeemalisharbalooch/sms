<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableWorkload extends Model
{
    protected $fillable = [
        'session_id',
        'class_id',
        'subject_id',
        'weekly_periods',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'weekly_periods' => 'integer',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
