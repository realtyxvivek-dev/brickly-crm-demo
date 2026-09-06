<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulksmsplans_ivr_settings', function (Blueprint $table) {
            $table->boolean('allow_tokenless_testing')->default(false)->after('fallback_user_id');
            $table->timestamp('tokenless_testing_expires_at')->nullable()->after('allow_tokenless_testing');
        });
    }

    public function down(): void
    {
        Schema::table('bulksmsplans_ivr_settings', function (Blueprint $table) {
            $table->dropColumn(['allow_tokenless_testing', 'tokenless_testing_expires_at']);
        });
    }
};
