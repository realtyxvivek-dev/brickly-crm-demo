<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('mcube_call_id')->nullable()->after('recording_url')->index();
            $table->string('mcube_agent_phone', 20)->nullable()->after('mcube_call_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn(['mcube_call_id', 'mcube_agent_phone']);
        });
    }
};
