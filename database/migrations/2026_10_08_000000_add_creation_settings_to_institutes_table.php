<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->string('short_name', 50)->nullable()->after('name');
            $table->string('currency_symbol', 10)->default('Rs')->after('attendance_mode');
            $table->string('timezone', 64)->default('Asia/Karachi')->after('currency_symbol');
            $table->unsignedTinyInteger('default_fee_due_date')->default(5)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'currency_symbol', 'timezone', 'default_fee_due_date']);
        });
    }
};
