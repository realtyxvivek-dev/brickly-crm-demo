<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('towers', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor_count')->nullable()->after('tower_number');
            $table->unsignedInteger('unit_count')->nullable()->after('floor_count');
        });
    }

    public function down(): void
    {
        Schema::table('towers', function (Blueprint $table) {
            $table->dropColumn(['floor_count', 'unit_count']);
        });
    }
};
