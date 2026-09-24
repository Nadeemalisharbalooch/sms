<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;

class Institute extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'favicon',
        'attendance_mode',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function ($institute) {
            if (empty($institute->public_id)) {
                $institute->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function instituteUsers(): HasMany
    {
        return $this->hasMany(InstituteUser::class);
    }

    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            InstituteUser::class,
            'institute_id',
            'id',
            'id',
            'user_id',
        )->where('institute_user.is_owner', true);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(InstituteSubscription::class)->latestOfMany();
    }
}
