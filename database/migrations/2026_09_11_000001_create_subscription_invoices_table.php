<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('institute_subscriptions')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('billing_interval', 20);
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'payment_submitted', 'paid', 'cancelled'])->default('pending');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
