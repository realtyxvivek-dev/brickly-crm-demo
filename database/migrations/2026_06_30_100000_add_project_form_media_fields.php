<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'rera_qr_path')) {
                $table->string('rera_qr_path')->nullable()->after('rera_no');
            }
        });

        Schema::table('unit_types', function (Blueprint $table) {
            if (!Schema::hasColumn('unit_types', 'floor_plan_image')) {
                $table->string('floor_plan_image')->nullable()->after('area_sqft');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            if (Schema::hasColumn('unit_types', 'floor_plan_image')) {
                $table->dropColumn('floor_plan_image');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'rera_qr_path')) {
                $table->dropColumn('rera_qr_path');
            }
        });
    }
};
