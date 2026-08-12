<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'institute_id', 'first_name', 'last_name', 'dob', 'gender',
        'guardian_name', 'guardian_phone', 'address', 'admission_date',
    ];

    protected function casts(): array
    {
        return ['institute_id' => 'integer', 'dob' => 'date', 'admission_date' => 'date'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
