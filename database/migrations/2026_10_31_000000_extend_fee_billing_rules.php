<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->string('type', 20)->default('recurring')->after('name');
            $table->json('default_months')->nullable()->after('type');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->json('charge_months')->nullable();
            $table->dropColumn('recurrence');
        });

        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->boolean('is_discount')->default(false)->after('amount');
            $table->json('charge_months')->nullable()->after('is_discount');
        });

        Schema::table('fee_vouchers', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 10, 2)->default(0)->after('total_amount');
            $table->boolean('late_fee_waived')->default(false)->after('late_fee_amount');
            $table->decimal('carried_forward_amount', 10, 2)->default(0)->after('late_fee_waived');
        });

        Schema::table('fee_voucher_items', function (Blueprint $table) {
            $table->string('type', 20)->default('charge')->after('amount');
            $table->renameColumn('fee_voucher_id', 'voucher_id');
            $table->renameColumn('fee_name', 'description');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->boolean('late_fee_waived')->default(false)->after('payment_method');
        });

        Schema::create('student_one_time_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained('fee_categories')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_billed')->default(false);
            $table->timestamps();
            $table->index(['session_id', 'student_id', 'is_billed']);
        });

        Schema::create('financial_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('late_fee_type', 20)->default('fixed');
            $table->decimal('late_fee_value', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::rename('fee_structures', 'class_fee_structures');
        Schema::rename('student_fee_assignments', 'student_fee_addons');
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_settings');
        Schema::dropIfExists('student_one_time_fees');
        Schema::table('fee_payments', fn (Blueprint $table) => $table->dropColumn('late_fee_waived'));
        Schema::table('fee_voucher_items', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->renameColumn('voucher_id', 'fee_voucher_id');
            $table->renameColumn('description', 'fee_name');
        });
        Schema::table('fee_vouchers', fn (Blueprint $table) => $table->dropColumn(['late_fee_amount', 'late_fee_waived', 'carried_forward_amount']));
        Schema::rename('student_fee_addons', 'student_fee_assignments');
        Schema::rename('class_fee_structures', 'fee_structures');
        Schema::table('student_fee_assignments', fn (Blueprint $table) => $table->dropColumn(['is_discount', 'charge_months']));
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn('charge_months');
            $table->string('recurrence', 20)->default('monthly');
        });
        Schema::table('fee_categories', fn (Blueprint $table) => $table->dropColumn(['type', 'default_months']));
    }
};