<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_attendance_profiles', function (Blueprint $table) {
            $table->boolean('attendance_enabled')->default(false)->after('salary_mode');
            $table->string('attendance_rollout_stage')->default('pilot')->after('attendance_enabled');
            $table->index(['attendance_enabled', 'attendance_rollout_stage'], 'user_attendance_profiles_rollout_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_attendance_profiles', function (Blueprint $table) {
            $table->dropIndex('user_attendance_profiles_rollout_idx');
            $table->dropColumn(['attendance_enabled', 'attendance_rollout_stage']);
        });
    }
};
