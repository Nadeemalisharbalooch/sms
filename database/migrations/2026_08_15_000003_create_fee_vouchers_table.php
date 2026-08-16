<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('billing_month', 7); // e.g. "2026-08"
            $table->date('due_date');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('status', 20)->default('unpaid'); // unpaid, partial, paid
            $table->timestamps();

            $table->unique(['session_id', 'student_id', 'billing_month']);
            $table->index(['session_id', 'student_id']);
            $table->index('billing_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_vouchers');
    }
};
