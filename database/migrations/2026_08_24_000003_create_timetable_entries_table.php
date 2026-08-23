<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained('timetable_time_slots')->cascadeOnDelete();
            $table->string('day_of_week', 20); // monday, tuesday, wednesday, thursday, friday, saturday, sunday
            $table->string('room_number', 50)->nullable();
            $table->timestamps();

            // Prevent assigning multiple subjects to same class/section at same day & slot
            $table->unique(
                ['session_id', 'class_id', 'section_id', 'day_of_week', 'time_slot_id'],
                'timetable_entries_session_class_section_day_slot_unique'
            );

            // Fast lookup for teacher clash detection
            $table->index(
                ['session_id', 'teacher_user_id', 'day_of_week', 'time_slot_id'],
                'timetable_entries_teacher_day_slot_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
