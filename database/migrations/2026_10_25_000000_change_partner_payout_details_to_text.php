<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_profiles', function (Blueprint $table) {
            $table->text('payout_details')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Encrypted values are not valid JSON, so converting back could fail.
    }
};
