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
        Schema::table('site_visits', function (Blueprint $table) {
            $table->string('visited_projects')->nullable()->after('project');
            $table->enum('tentative_closing_time', [
                'within_3_days',
                'tomorrow',
                'this_week',
                'this_month',
                'it_will_take_time'
            ])->nullable()->after('visited_projects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropColumn(['visited_projects', 'tentative_closing_time']);
        });
    }
};
