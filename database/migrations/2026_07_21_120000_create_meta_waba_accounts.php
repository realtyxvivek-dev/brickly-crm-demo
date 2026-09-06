<?php

use App\Models\MetaWabaSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meta_waba_accounts')) {
            Schema::create('meta_waba_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(false);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->string('graph_version')->default('v20.0');
                $table->string('phone_number_id')->nullable();
                $table->string('waba_id')->nullable();
                $table->string('business_account_id')->nullable();
                $table->string('meta_business_id')->nullable();
                $table->string('meta_app_id')->nullable();
                $table->string('embedded_signup_configuration_id')->nullable();
                $table->json('embedded_signup_response')->nullable();
                $table->string('connection_status')->default('manual');
                $table->text('last_error')->nullable();
                $table->text('access_token')->nullable();
                $table->string('webhook_verify_token')->nullable();
                $table->text('app_secret')->nullable();
                $table->string('privacy_policy_url', 2000)->nullable();
                $table->string('terms_url', 2000)->nullable();
                $table->string('data_deletion_url', 2000)->nullable();
                $table->string('display_phone_number')->nullable();
                $table->string('verified_name')->nullable();
                $table->string('quality_rating')->nullable();
                $table->json('last_verified_response')->nullable();
                $table->timestamp('templates_synced_at')->nullable();
                $table->unsignedInteger('last_template_sync_count')->nullable();
                $table->boolean('voice_calls_enabled')->default(false);
                $table->boolean('display_call_buttons')->default(false);
                $table->boolean('callbacks_enabled')->default(false);
                $table->json('call_hours')->nullable();
                $table->timestamp('call_pause_until')->nullable();
                $table->json('call_settings_last_response')->nullable();
                $table->timestamps();

                $table->index(['is_default', 'is_active']);
                $table->index('phone_number_id');
                $table->index('waba_id');
            });
        }

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_templates', 'meta_waba_account_id')) {
                $table->foreignId('meta_waba_account_id')->nullable()->after('provider')->constrained('meta_waba_accounts')->nullOnDelete();
                $table->index(['provider', 'meta_waba_account_id']);
            }
        });

        Schema::table('waba_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('waba_campaigns', 'meta_waba_account_id')) {
                $table->foreignId('meta_waba_account_id')->nullable()->after('template_id')->constrained('meta_waba_accounts')->nullOnDelete();
            }
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'meta_waba_account_id')) {
                $table->foreignId('meta_waba_account_id')->nullable()->after('provider')->constrained('meta_waba_accounts')->nullOnDelete();
            }
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'meta_waba_account_id')) {
                $table->foreignId('meta_waba_account_id')->nullable()->after('lead_id')->constrained('meta_waba_accounts')->nullOnDelete();
            }
        });

        $settings = MetaWabaSettings::query()->first();
        if ($settings && ($settings->phone_number_id || $settings->waba_id || $settings->access_token)) {
            $accountId = DB::table('meta_waba_accounts')->where('phone_number_id', $settings->phone_number_id)->value('id');
            $payload = [
                'name' => $settings->display_phone_number ?: $settings->verified_name ?: 'Default Meta WABA',
                'connected_by_user_id' => $settings->connected_by_user_id,
                'is_active' => (bool) $settings->is_active,
                'is_default' => true,
                'is_verified' => (bool) $settings->is_verified,
                'verified_at' => $settings->verified_at,
                'graph_version' => $settings->graph_version ?: 'v20.0',
                'phone_number_id' => $settings->phone_number_id,
                'waba_id' => $settings->waba_id,
                'business_account_id' => $settings->business_account_id,
                'meta_business_id' => $settings->meta_business_id,
                'meta_app_id' => $settings->meta_app_id,
                'embedded_signup_configuration_id' => $settings->embedded_signup_configuration_id,
                'embedded_signup_response' => $settings->embedded_signup_response ? json_encode($settings->embedded_signup_response) : null,
                'connection_status' => $settings->connection_status ?: 'manual',
                'last_error' => $settings->last_error,
                'access_token' => $settings->getRawOriginal('access_token'),
                'webhook_verify_token' => $settings->webhook_verify_token,
                'app_secret' => $settings->getRawOriginal('app_secret'),
                'privacy_policy_url' => $settings->privacy_policy_url,
                'terms_url' => $settings->terms_url,
                'data_deletion_url' => $settings->data_deletion_url,
                'display_phone_number' => $settings->display_phone_number,
                'verified_name' => $settings->verified_name,
                'quality_rating' => $settings->quality_rating,
                'last_verified_response' => $settings->last_verified_response ? json_encode($settings->last_verified_response) : null,
                'templates_synced_at' => $settings->templates_synced_at,
                'last_template_sync_count' => $settings->last_template_sync_count,
                'voice_calls_enabled' => (bool) $settings->voice_calls_enabled,
                'display_call_buttons' => (bool) $settings->display_call_buttons,
                'callbacks_enabled' => (bool) $settings->callbacks_enabled,
                'call_hours' => $settings->call_hours ? json_encode($settings->call_hours) : null,
                'call_pause_until' => $settings->call_pause_until,
                'call_settings_last_response' => $settings->call_settings_last_response ? json_encode($settings->call_settings_last_response) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($accountId) {
                DB::table('meta_waba_accounts')->where('id', $accountId)->update($payload);
            } else {
                $accountId = DB::table('meta_waba_accounts')->insertGetId($payload);
            }

            DB::table('meta_waba_accounts')->where('id', '!=', $accountId)->update(['is_default' => false]);
            DB::table('whatsapp_templates')->where('provider', 'meta_waba')->whereNull('meta_waba_account_id')->update(['meta_waba_account_id' => $accountId]);
            DB::table('waba_campaigns')->whereNull('meta_waba_account_id')->update(['meta_waba_account_id' => $accountId]);
            DB::table('whatsapp_messages')->where('provider', 'meta_waba')->whereNull('meta_waba_account_id')->update(['meta_waba_account_id' => $accountId]);
            DB::table('whatsapp_conversations')->whereNull('meta_waba_account_id')->update(['meta_waba_account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'meta_waba_account_id')) {
                $table->dropConstrainedForeignId('meta_waba_account_id');
            }
        });
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_messages', 'meta_waba_account_id')) {
                $table->dropConstrainedForeignId('meta_waba_account_id');
            }
        });
        Schema::table('waba_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('waba_campaigns', 'meta_waba_account_id')) {
                $table->dropConstrainedForeignId('meta_waba_account_id');
            }
        });
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_templates', 'meta_waba_account_id')) {
                $table->dropConstrainedForeignId('meta_waba_account_id');
            }
        });
        Schema::dropIfExists('meta_waba_accounts');
    }
};
