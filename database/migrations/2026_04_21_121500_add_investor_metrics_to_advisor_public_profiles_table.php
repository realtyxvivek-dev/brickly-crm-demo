<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisor_public_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('investor_portfolio_value')->default(0)->after('happy_families_served');
            $table->unsignedInteger('active_investors')->default(0)->after('investor_portfolio_value');
            $table->unsignedInteger('nri_investors_assisted')->default(0)->after('active_investors');
            $table->unsignedInteger('bookings_this_quarter')->default(0)->after('nri_investors_assisted');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_public_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'investor_portfolio_value',
                'active_investors',
                'nri_investors_assisted',
                'bookings_this_quarter',
            ]);
        });
    }
};
