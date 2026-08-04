<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password','is_institute','is_active','is_admin','address','phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, SoftDeletes, HasApiTokens, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function instituteUsers(): HasMany
    {
        return $this->hasMany(InstituteUser::class);
    }

    public function subjectTeachers(): HasMany
    {
        return $this->hasMany(SubjectTeacher::class, 'teacher_id');
    }

    public function sectionTeachers(): HasMany
    {
        return $this->hasMany(SectionTeacher::class, 'teacher_id');
    }

    public function subjectAllocations(): HasMany
    {
        return $this->hasMany(SubjectAllocation::class, 'teacher_user_id');
    }

    public function roomTeachers(): HasMany
    {
        return $this->hasMany(RoomTeacher::class, 'teacher_user_id');
    }

    // If you want to restrict to certain role types/guards later, you can customize here.
    // For now, default Spatie behavior is used.
}
