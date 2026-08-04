<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionTeacher extends Model
{
    protected $table = 'section_teacher';

    protected $fillable = ['section_id', 'teacher_id'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(AcademicSection::class, 'section_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
