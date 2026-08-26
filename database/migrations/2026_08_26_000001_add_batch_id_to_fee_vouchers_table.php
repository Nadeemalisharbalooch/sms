<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::table('fee_vouchers', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('fee_vouchers', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
