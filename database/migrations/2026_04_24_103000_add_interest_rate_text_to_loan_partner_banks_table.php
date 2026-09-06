<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_partner_banks', function (Blueprint $table) {
            $table->string('interest_rate_text', 60)->nullable()->after('short_offer_text');
        });
    }

    public function down(): void
    {
        Schema::table('loan_partner_banks', function (Blueprint $table) {
            $table->dropColumn('interest_rate_text');
        });
    }
};
