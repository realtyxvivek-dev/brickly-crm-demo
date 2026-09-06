<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_automation_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('source_automation_rules', 'source_type')) {
                $table->string('source_type', 80)->nullable()->after('source');
            }
            if (!Schema::hasColumn('source_automation_rules', 'source_id')) {
                $table->string('source_id', 120)->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('source_automation_rules', 'source_label')) {
                $table->string('source_label')->nullable()->after('source_id');
            }
            if (!Schema::hasColumn('source_automation_rules', 'distribution_method')) {
                $table->string('distribution_method', 40)->nullable()->after('google_sheet_config_id');
            }
            if (!Schema::hasColumn('source_automation_rules', 'task_enabled')) {
                $table->boolean('task_enabled')->default(true)->after('auto_create_task');
            }
            if (!Schema::hasColumn('source_automation_rules', 'notification_enabled')) {
                $table->boolean('notification_enabled')->default(true)->after('task_enabled');
            }
            if (!Schema::hasColumn('source_automation_rules', 'skip_lead_off_users')) {
                $table->boolean('skip_lead_off_users')->default(true)->after('fallback_user_id');
            }
            if (!Schema::hasColumn('source_automation_rules', 'duplicate_handling')) {
                $table->string('duplicate_handling', 80)->default('keep_existing_owner_mark_reenquiry')->after('skip_lead_off_users');
            }
            $table->index(['source_type', 'source_id', 'is_active'], 'source_auto_rules_generic_lookup_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE source_automation_rules
                MODIFY COLUMN source ENUM(
                    'facebook_lead_ads',
                    'pabbly',
                    'mcube',
                    'ivr',
                    'google_sheets',
                    'csv',
                    'manual_import',
                    'website',
                    'whatsapp',
                    'instagram',
                    '99acres',
                    'all'
                ) NOT NULL
            ");
        }

        DB::table('source_automation_rules')
            ->whereNull('source_type')
            ->orderBy('id')
            ->chunkById(100, function ($rules) {
                foreach ($rules as $rule) {
                    $source = $rule->source === 'mcube' ? 'ivr' : $rule->source;
                    $sourceId = null;
                    if ($rule->fb_form_id) {
                        $sourceId = (string) $rule->fb_form_id;
                    } elseif (isset($rule->google_sheet_config_id) && $rule->google_sheet_config_id) {
                        $sourceId = (string) $rule->google_sheet_config_id;
                    }

                    DB::table('source_automation_rules')
                        ->where('id', $rule->id)
                        ->update([
                            'source_type' => $source,
                            'source_id' => $sourceId,
                            'source_label' => $rule->name,
                            'distribution_method' => $rule->assignment_method,
                            'task_enabled' => (bool) $rule->auto_create_task,
                            'notification_enabled' => true,
                            'skip_lead_off_users' => true,
                            'duplicate_handling' => 'keep_existing_owner_mark_reenquiry',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('source_automation_rules', function (Blueprint $table) {
            try {
                $table->dropIndex('source_auto_rules_generic_lookup_idx');
            } catch (\Throwable $e) {
                // Index may not exist on partially migrated local databases.
            }
        });

        Schema::table('source_automation_rules', function (Blueprint $table) {
            foreach ([
                'source_type',
                'source_id',
                'source_label',
                'distribution_method',
                'task_enabled',
                'notification_enabled',
                'skip_lead_off_users',
                'duplicate_handling',
            ] as $column) {
                if (Schema::hasColumn('source_automation_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
