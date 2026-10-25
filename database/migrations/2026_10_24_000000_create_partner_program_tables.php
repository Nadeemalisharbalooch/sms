<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_partner')->default(false)->after('is_institute');
        });

        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('phone', 30);
            $table->string('website_url')->nullable();
            $table->json('promotion_methods');
            $table->text('tax_id_or_cnic');
            $table->string('referral_code', 32)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->decimal('commission_rate', 5, 2)->default(20);
            $table->string('payout_method', 30)->nullable();
            // Laravel's encrypted:array cast stores an encrypted string, not JSON.
            $table->text('payout_details')->nullable();
            $table->timestamp('terms_agreed_at');
            $table->timestamps();
        });

        Schema::create('partner_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['partner_profile_id', 'created_at']);
        });

        Schema::create('partner_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_invoice_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('clears_at')->nullable();
            $table->timestamps();
            $table->index(['partner_profile_id', 'institute_id']);
        });

        Schema::create('partner_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('transaction_reference')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_withdrawals');
        Schema::dropIfExists('partner_commissions');
        Schema::dropIfExists('partner_clicks');
        Schema::dropIfExists('partner_profiles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_partner'));
    }
};
