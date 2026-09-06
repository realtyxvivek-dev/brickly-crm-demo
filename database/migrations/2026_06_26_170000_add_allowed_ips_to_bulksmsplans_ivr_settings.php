<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulksmsplans_ivr_settings', function (Blueprint $table) {
            $table->json('allowed_webhook_ips')->nullable()->after('tokenless_testing_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bulksmsplans_ivr_settings', function (Blueprint $table) {
            $table->dropColumn('allowed_webhook_ips');
        });
    }
};
