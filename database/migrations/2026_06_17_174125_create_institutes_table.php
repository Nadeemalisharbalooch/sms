<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('institutes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
                $table->string('name')->nullable();
                 $table->string('email')->nullable();
             $table->string('phone')->nullable();
             $table->text('address')->nullable();
             $table->string('logo')->nullable();
             $table->string('favicon')->nullable();
              $table->enum('attendance_mode', ['class', 'subject'])
              ->default('class');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institutes');
    }
};
