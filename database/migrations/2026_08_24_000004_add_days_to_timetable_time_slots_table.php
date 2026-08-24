<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_time_slots') && ! Schema::hasColumn('timetable_time_slots', 'days')) {
            Schema::table('timetable_time_slots', function (Blueprint $table) {
                $table->json('days')->nullable()->after('is_break');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timetable_time_slots') && Schema::hasColumn('timetable_time_slots', 'days')) {
            Schema::table('timetable_time_slots', function (Blueprint $table) {
                $table->dropColumn('days');
            });
        }
    }
};
