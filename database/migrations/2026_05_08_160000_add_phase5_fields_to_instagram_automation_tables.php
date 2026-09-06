<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ig_conversations', function (Blueprint $table) {
            $table->json('collected_fields')->nullable()->after('automation_rule_id');
            $table->foreignId('duplicate_lead_id')->nullable()->after('lead_id')->constrained('leads')->nullOnDelete();
            $table->timestamp('duplicate_checked_at')->nullable()->after('duplicate_lead_id');
            $table->timestamp('completed_at')->nullable()->after('status');

            $table->index(['duplicate_lead_id', 'duplicate_checked_at'], 'ig_conversations_duplicate_lead_idx');
            $table->index(['status', 'completed_at'], 'ig_conversations_status_completed_idx');
        });

        Schema::table('ig_messages', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('payload');
            $table->index(['direction', 'received_at'], 'ig_messages_direction_received_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE source_automation_rules
                MODIFY COLUMN source ENUM(
                    'facebook_lead_ads',
                    'pabbly',
                    'mcube',
                    'google_sheets',
                    'csv',
                    'website',
                    'instagram',
                    'all'
                ) NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('source_automation_rules')
                ->where('source', 'instagram')
                ->update(['source' => 'all']);

            DB::statement("
                ALTER TABLE source_automation_rules
                MODIFY COLUMN source ENUM(
                    'facebook_lead_ads',
                    'pabbly',
                    'mcube',
                    'google_sheets',
                    'csv',
                    'website',
                    'all'
                ) NOT NULL
            ");
        }

        Schema::table('ig_messages', function (Blueprint $table) {
            $table->dropIndex('ig_messages_direction_received_idx');
            $table->dropColumn('received_at');
        });

        Schema::table('ig_conversations', function (Blueprint $table) {
            $table->dropIndex('ig_conversations_status_completed_idx');
            $table->dropIndex('ig_conversations_duplicate_lead_idx');
            $table->dropConstrainedForeignId('duplicate_lead_id');
            $table->dropColumn([
                'collected_fields',
                'duplicate_checked_at',
                'completed_at',
            ]);
        });
    }
};
