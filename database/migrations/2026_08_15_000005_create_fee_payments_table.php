<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_voucher_id')->constrained('fee_vouchers')->cascadeOnDelete();
            $table->decimal('amount_paid', 10, 2);
            $table->date('payment_date');
            $table->string('payment_method', 20); // cash, bank
            $table->foreignId('collected_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('fee_voucher_id');
            $table->index(['payment_date', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
