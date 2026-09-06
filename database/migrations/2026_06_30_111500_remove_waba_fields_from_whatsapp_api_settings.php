<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_api_settings', function (Blueprint $table) {
            foreach ([
                'app_secret',
                'webhook_verify_token',
                'access_token',
                'business_account_id',
                'waba_id',
                'phone_number_id',
                'graph_version',
                'provider',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_api_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_api_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_api_settings', 'provider')) {
                $table->string('provider')->default('third_party')->after('id');
            }
            if (!Schema::hasColumn('whatsapp_api_settings', 'graph_version')) {
                $table->string('graph_version')->default('v20.0')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_api_settings', 'phone_number_id')) {
                $table->string('phone_number_id')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_api_settings', 'waba_id')) {
                $table->string('waba_id')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_api_settings', 'business_account_id')) {
                $table->string('business_account_id')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_api_settings', 'access_token')) {
                $table->text('access_token')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_api_settings', 'webhook_verify_token')) {
                $table->string('webhook_verify_token')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_api_settings', 'app_secret')) {
                $table->text('app_secret')->nullable();
            }
        });
    }
};
