<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_pdf_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->json('filters');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['institute_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_pdf_exports');
    }
};
