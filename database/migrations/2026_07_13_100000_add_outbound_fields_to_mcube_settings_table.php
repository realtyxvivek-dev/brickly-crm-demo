<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcube_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('mcube_settings', 'outbound_enabled')) {
                $table->boolean('outbound_enabled')->default(false)->after('is_enabled');
            }
            if (!Schema::hasColumn('mcube_settings', 'outbound_api_url')) {
                $table->string('outbound_api_url')->nullable()->after('outbound_enabled');
            }
            if (!Schema::hasColumn('mcube_settings', 'outbound_token')) {
                $table->string('outbound_token')->nullable()->after('outbound_api_url');
            }
            if (!Schema::hasColumn('mcube_settings', 'outbound_auth_mode')) {
                $table->string('outbound_auth_mode', 40)->default('json_http_authorization')->after('outbound_token');
            }
            if (!Schema::hasColumn('mcube_settings', 'default_refurl')) {
                $table->string('default_refurl', 255)->default('1')->after('outbound_auth_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcube_settings', function (Blueprint $table) {
            $columns = [
                'default_refurl',
                'outbound_auth_mode',
                'outbound_token',
                'outbound_api_url',
                'outbound_enabled',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('mcube_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
