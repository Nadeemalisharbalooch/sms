<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrade databases created before billing was decoupled. Fresh installs
     * already receive the target schema from the original create migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('institute_subscriptions', 'blocked')) {
            Schema::table('institute_subscriptions', function (Blueprint $table) {
                $table->boolean('blocked')->default(false)->after('status');
            });
        }

        // Convert enum columns to strings before translating legacy values.
        Schema::table('institute_subscriptions', function (Blueprint $table) {
            $table->string('status', 20)->default('trialing')->change();
        });
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
            $table->foreignId('subscription_id')->nullable()->change();
        });

        DB::table('institute_subscriptions')->where('status', 'pending')->update(['status' => 'canceled', 'blocked' => true]);
        DB::table('institute_subscriptions')->where('status', 'trial')->update(['status' => 'trialing']);
        DB::table('institute_subscriptions')->where('status', 'cancelled')->update(['status' => 'canceled', 'blocked' => true]);
        DB::table('subscription_invoices')->where('status', 'pending')->update(['status' => 'open']);
        DB::table('subscription_invoices')->where('status', 'payment_submitted')->update(['status' => 'verification_pending']);
        DB::table('subscription_invoices')->where('status', 'cancelled')->update(['status' => 'void']);
    }

    public function down(): void
    {
        // Downgrading would require lossy status mappings, so retain data.
    }
};
