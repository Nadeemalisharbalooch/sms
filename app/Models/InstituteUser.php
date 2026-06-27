<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstituteUser extends Model
{
     protected $table = 'institute_user';
    //
    protected $fillable = ['institute_id','user_id','is_owner','is_active'];
}
