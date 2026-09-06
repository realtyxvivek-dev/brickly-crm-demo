<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_routing_rules')) {
            Schema::create('whatsapp_routing_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('priority')->default(100);
                $table->json('conditions')->nullable();
                $table->foreignId('meta_waba_account_id')->constrained('meta_waba_accounts')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'priority'], 'wa_routing_active_priority_idx');
            });
        }

        Schema::table('whatsapp_automation_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_automation_rules', 'meta_waba_account_id')) {
                $table->foreignId('meta_waba_account_id')->nullable()->after('template_id')->constrained('meta_waba_accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('whatsapp_automation_rules', 'cooldown_minutes')) {
                $table->unsignedInteger('cooldown_minutes')->default(0)->after('resend_cap');
            }
            if (!Schema::hasColumn('whatsapp_automation_rules', 'daily_send_cap')) {
                $table->unsignedInteger('daily_send_cap')->default(3)->after('cooldown_minutes');
            }
            if (!Schema::hasColumn('whatsapp_automation_rules', 'requires_session_window')) {
                $table->boolean('requires_session_window')->default(false)->after('daily_send_cap');
            }
            if (!Schema::hasColumn('whatsapp_automation_rules', 'fallback_action')) {
                $table->string('fallback_action')->nullable()->after('requires_session_window');
            }
        });

        Schema::table('whatsapp_automation_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_automation_logs', 'meta_waba_account_id')) {
                $table->foreignId('meta_waba_account_id')->nullable()->after('template_name')->constrained('meta_waba_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_automation_logs', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_automation_logs', 'meta_waba_account_id')) {
                $table->dropConstrainedForeignId('meta_waba_account_id');
            }
        });

        Schema::table('whatsapp_automation_rules', function (Blueprint $table) {
            foreach (['fallback_action', 'requires_session_window', 'daily_send_cap', 'cooldown_minutes'] as $column) {
                if (Schema::hasColumn('whatsapp_automation_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('whatsapp_automation_rules', 'meta_waba_account_id')) {
                $table->dropConstrainedForeignId('meta_waba_account_id');
            }
        });

        Schema::dropIfExists('whatsapp_routing_rules');
    }
};
