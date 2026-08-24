<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_break')->default(false);
            $table->json('days')->nullable(); // e.g. ['monday', 'tuesday', 'wednesday', 'thursday', 'saturday'] or ['friday'], null = all days
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['institute_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_time_slots');
    }
};
