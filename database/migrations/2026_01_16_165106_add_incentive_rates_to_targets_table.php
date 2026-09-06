<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->decimal('incentive_per_closer', 10, 2)->nullable()->after('target_closers');
            $table->decimal('incentive_per_visit', 10, 2)->nullable()->after('incentive_per_closer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn(['incentive_per_closer', 'incentive_per_visit']);
        });
    }
};
