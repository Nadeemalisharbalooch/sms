<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_voucher_id')->constrained('fee_vouchers')->cascadeOnDelete();
            $table->string('fee_name');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index('fee_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_voucher_items');
    }
};
