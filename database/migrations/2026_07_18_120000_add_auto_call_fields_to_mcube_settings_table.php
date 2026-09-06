<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcube_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('mcube_settings', 'auto_call_on_assignment')) {
                $table->boolean('auto_call_on_assignment')->default(false)->after('default_refurl');
            }
            if (!Schema::hasColumn('mcube_settings', 'auto_call_cooldown_minutes')) {
                $table->unsignedSmallInteger('auto_call_cooldown_minutes')->default(10)->after('auto_call_on_assignment');
            }
            if (!Schema::hasColumn('mcube_settings', 'auto_call_quiet_start')) {
                $table->time('auto_call_quiet_start')->nullable()->default('20:00:00')->after('auto_call_cooldown_minutes');
            }
            if (!Schema::hasColumn('mcube_settings', 'auto_call_quiet_end')) {
                $table->time('auto_call_quiet_end')->nullable()->default('09:00:00')->after('auto_call_quiet_start');
            }
            if (!Schema::hasColumn('mcube_settings', 'auto_call_allowed_sources')) {
                $table->json('auto_call_allowed_sources')->nullable()->after('auto_call_quiet_end');
            }
            if (!Schema::hasColumn('mcube_settings', 'auto_call_allowed_user_ids')) {
                $table->json('auto_call_allowed_user_ids')->nullable()->after('auto_call_allowed_sources');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcube_settings', function (Blueprint $table) {
            foreach ([
                'auto_call_allowed_user_ids',
                'auto_call_allowed_sources',
                'auto_call_quiet_end',
                'auto_call_quiet_start',
                'auto_call_cooldown_minutes',
                'auto_call_on_assignment',
            ] as $column) {
                if (Schema::hasColumn('mcube_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
