<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_workloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('weekly_periods')->default(5);
            $table->timestamps();

            $table->unique(
                ['session_id', 'class_id', 'subject_id'],
                'timetable_workloads_session_class_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_workloads');
    }
};
