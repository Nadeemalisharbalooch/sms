<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('billing_month', 7);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['institute_id', 'session_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_batches');
    }
};
