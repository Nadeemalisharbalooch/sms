<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
