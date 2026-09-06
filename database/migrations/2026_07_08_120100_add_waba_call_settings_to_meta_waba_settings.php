<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_waba_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('meta_waba_settings', 'voice_calls_enabled')) {
                $table->boolean('voice_calls_enabled')->default(false)->after('last_verified_response');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'display_call_buttons')) {
                $table->boolean('display_call_buttons')->default(true)->after('voice_calls_enabled');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'callbacks_enabled')) {
                $table->boolean('callbacks_enabled')->default(false)->after('display_call_buttons');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'call_hours')) {
                $table->json('call_hours')->nullable()->after('callbacks_enabled');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'call_pause_until')) {
                $table->timestamp('call_pause_until')->nullable()->after('call_hours');
            }
            if (!Schema::hasColumn('meta_waba_settings', 'call_settings_last_response')) {
                $table->json('call_settings_last_response')->nullable()->after('call_pause_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meta_waba_settings', function (Blueprint $table) {
            foreach (['call_settings_last_response', 'call_pause_until', 'call_hours', 'callbacks_enabled', 'display_call_buttons', 'voice_calls_enabled'] as $column) {
                if (Schema::hasColumn('meta_waba_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
