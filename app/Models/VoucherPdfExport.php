<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherPdfExport extends Model
{
    protected $fillable = ['institute_id', 'user_id', 'status', 'filters', 'file_path', 'error_message'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
