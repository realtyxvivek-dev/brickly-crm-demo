<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dateTime('lead_off_start_at')->nullable()->after('absent_until');
            $table->dateTime('lead_off_end_at')->nullable()->after('lead_off_start_at');
            $table->string('lead_off_source', 20)->nullable()->after('lead_off_end_at');
            $table->unsignedBigInteger('lead_off_set_by')->nullable()->after('lead_off_source');
            $table->index('lead_off_start_at');
            $table->index('lead_off_end_at');
        });

        Schema::table('telecaller_profiles', function (Blueprint $table) {
            $table->dateTime('lead_off_start_at')->nullable()->after('absent_until');
            $table->dateTime('lead_off_end_at')->nullable()->after('lead_off_start_at');
            $table->index('lead_off_start_at');
            $table->index('lead_off_end_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex(['lead_off_start_at']);
            $table->dropIndex(['lead_off_end_at']);
            $table->dropColumn(['lead_off_start_at', 'lead_off_end_at', 'lead_off_source', 'lead_off_set_by']);
        });

        Schema::table('telecaller_profiles', function (Blueprint $table) {
            $table->dropIndex(['lead_off_start_at']);
            $table->dropIndex(['lead_off_end_at']);
            $table->dropColumn(['lead_off_start_at', 'lead_off_end_at']);
        });
    }
};
