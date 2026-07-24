<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('institute_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['institute_id', 'name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['institute_id', 'name', 'guard_name']);
            $table->dropConstrainedForeignId('institute_id');
            $table->unique(['name', 'guard_name']);
        });
    }
};
