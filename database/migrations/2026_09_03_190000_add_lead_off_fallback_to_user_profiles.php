<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_off_fallback_user_id')->nullable()->after('lead_off_set_by');
            $table->index('lead_off_fallback_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex(['lead_off_fallback_user_id']);
            $table->dropColumn('lead_off_fallback_user_id');
        });
    }
};
