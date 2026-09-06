<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_waba_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('meta_waba_settings', 'connected_by_user_id')) {
                $table->foreignId('connected_by_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('meta_waba_settings', 'meta_business_id')) {
                $table->string('meta_business_id')->nullable()->after('business_account_id');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'meta_app_id')) {
                $table->string('meta_app_id')->nullable()->after('meta_business_id');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'embedded_signup_configuration_id')) {
                $table->string('embedded_signup_configuration_id')->nullable()->after('meta_app_id');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'embedded_signup_response')) {
                $table->json('embedded_signup_response')->nullable()->after('embedded_signup_configuration_id');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'connection_status')) {
                $table->string('connection_status', 40)->default('manual')->after('embedded_signup_response');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'last_error')) {
                $table->text('last_error')->nullable()->after('connection_status');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'privacy_policy_url')) {
                $table->string('privacy_policy_url')->nullable()->after('app_secret');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'terms_url')) {
                $table->string('terms_url')->nullable()->after('privacy_policy_url');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'data_deletion_url')) {
                $table->string('data_deletion_url')->nullable()->after('terms_url');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'templates_synced_at')) {
                $table->timestamp('templates_synced_at')->nullable()->after('last_verified_response');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'last_template_sync_count')) {
                $table->unsignedInteger('last_template_sync_count')->default(0)->after('templates_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meta_waba_settings', function (Blueprint $table) {
            if (Schema::hasColumn('meta_waba_settings', 'connected_by_user_id')) {
                $table->dropForeign(['connected_by_user_id']);
                $table->dropColumn('connected_by_user_id');
            }

            foreach ([
                'meta_business_id',
                'meta_app_id',
                'embedded_signup_configuration_id',
                'embedded_signup_response',
                'connection_status',
                'last_error',
                'privacy_policy_url',
                'terms_url',
                'data_deletion_url',
                'templates_synced_at',
                'last_template_sync_count',
            ] as $column) {
                if (Schema::hasColumn('meta_waba_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
