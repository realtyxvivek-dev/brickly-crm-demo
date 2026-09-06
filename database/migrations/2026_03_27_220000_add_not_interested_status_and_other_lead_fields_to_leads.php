<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'other_lead_marked_by')) {
                $table->unsignedBigInteger('other_lead_marked_by')->nullable()->after('pending_manager_id');
                $table->index('other_lead_marked_by');
            }

            if (!Schema::hasColumn('leads', 'other_lead_marked_at')) {
                $table->timestamp('other_lead_marked_at')->nullable()->after('other_lead_marked_by');
                $table->index('other_lead_marked_at');
            }

            if (!Schema::hasColumn('leads', 'other_lead_reason')) {
                $table->text('other_lead_reason')->nullable()->after('other_lead_marked_at');
            }
        });

        DB::statement("
            ALTER TABLE leads
            MODIFY COLUMN status ENUM(
                'new',
                'connected',
                'verified_prospect',
                'meeting_scheduled',
                'meeting_completed',
                'visit_scheduled',
                'visit_done',
                'revisited_scheduled',
                'revisited_completed',
                'closed',
                'dead',
                'junk',
                'not_interested',
                'on_hold'
            ) DEFAULT 'new'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE leads
            SET status = 'closed'
            WHERE status = 'not_interested'
        ");

        DB::statement("
            ALTER TABLE leads
            MODIFY COLUMN status ENUM(
                'new',
                'connected',
                'verified_prospect',
                'meeting_scheduled',
                'meeting_completed',
                'visit_scheduled',
                'visit_done',
                'revisited_scheduled',
                'revisited_completed',
                'closed',
                'dead',
                'junk',
                'on_hold'
            ) DEFAULT 'new'
        ");

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'other_lead_reason')) {
                $table->dropColumn('other_lead_reason');
            }

            if (Schema::hasColumn('leads', 'other_lead_marked_at')) {
                $table->dropIndex(['other_lead_marked_at']);
                $table->dropColumn('other_lead_marked_at');
            }

            if (Schema::hasColumn('leads', 'other_lead_marked_by')) {
                $table->dropIndex(['other_lead_marked_by']);
                $table->dropColumn('other_lead_marked_by');
            }
        });
    }
};
