<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'outcome')) {
                $table->string('outcome', 50)->nullable()->after('status');
                $table->index('outcome');
            }

            if (!Schema::hasColumn('tasks', 'outcome_remark')) {
                $table->text('outcome_remark')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('tasks', 'outcome_recorded_at')) {
                $table->dateTime('outcome_recorded_at')->nullable()->after('completed_at');
            }

            if (!Schema::hasColumn('tasks', 'next_action_at')) {
                $table->dateTime('next_action_at')->nullable()->after('outcome_recorded_at');
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
                'on_hold'
            ) DEFAULT 'new'
        ");
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'next_action_at')) {
                $table->dropColumn('next_action_at');
            }

            if (Schema::hasColumn('tasks', 'outcome_recorded_at')) {
                $table->dropColumn('outcome_recorded_at');
            }

            if (Schema::hasColumn('tasks', 'outcome_remark')) {
                $table->dropColumn('outcome_remark');
            }

            if (Schema::hasColumn('tasks', 'outcome')) {
                $table->dropIndex(['outcome']);
                $table->dropColumn('outcome');
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
                'on_hold'
            ) DEFAULT 'new'
        ");
    }
};
