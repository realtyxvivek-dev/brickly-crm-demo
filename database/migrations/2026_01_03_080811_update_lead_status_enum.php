<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add new column for auto-update flag (if not exists)
        if (!Schema::hasColumn('leads', 'status_auto_update_enabled')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->boolean('status_auto_update_enabled')->default(true)->after('status');
            });
        }

        // Step 2: First, expand enum to include all old and new values
        DB::statement("
            ALTER TABLE leads 
            MODIFY COLUMN status ENUM(
                'new',
                'contacted',
                'qualified',
                'site_visit_scheduled',
                'site_visit_completed',
                'negotiation',
                'closed_won',
                'closed_lost',
                'on_hold',
                'connected',
                'verified_prospect',
                'meeting_scheduled',
                'meeting_completed',
                'visit_scheduled',
                'visit_done',
                'revisited_scheduled',
                'revisited_completed',
                'closed',
                'dead'
            ) DEFAULT 'new'
        ");

        // Step 3: Migrate existing data - update old status values to new ones
        DB::statement("
            UPDATE leads 
            SET status = CASE 
                WHEN status = 'closed_won' THEN 'closed'
                WHEN status = 'closed_lost' AND is_dead = 1 THEN 'dead'
                WHEN status = 'closed_lost' AND is_dead = 0 THEN 'closed'
                WHEN status = 'qualified' AND verified_at IS NOT NULL THEN 'verified_prospect'
                WHEN status = 'qualified' THEN 'verified_prospect'
                WHEN status = 'site_visit_scheduled' THEN (
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM site_visits 
                            WHERE site_visits.lead_id = leads.id 
                            AND site_visits.lead_type = 'Revisited'
                            ORDER BY site_visits.created_at DESC 
                            LIMIT 1
                        ) THEN 'revisited_scheduled'
                        ELSE 'visit_scheduled'
                    END
                )
                WHEN status = 'site_visit_completed' THEN (
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM site_visits 
                            WHERE site_visits.lead_id = leads.id 
                            AND site_visits.status = 'completed'
                            AND site_visits.lead_type = 'Revisited'
                            ORDER BY site_visits.completed_at DESC 
                            LIMIT 1
                        ) THEN 'revisited_completed'
                        ELSE 'visit_done'
                    END
                )
                WHEN status = 'contacted' THEN 'connected'
                ELSE status
            END
        ");

        // Step 4: Disable auto-update for leads that are dead or closed
        DB::statement("
            UPDATE leads 
            SET status_auto_update_enabled = false 
            WHERE status IN ('dead', 'closed')
        ");

        // Step 5: Now remove old enum values, keeping only new ones
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert data migration
        DB::statement("
            UPDATE leads 
            SET status = CASE 
                WHEN status = 'closed' THEN 'closed_won'
                WHEN status = 'dead' THEN 'closed_lost'
                WHEN status = 'verified_prospect' THEN 'qualified'
                WHEN status = 'visit_scheduled' THEN 'site_visit_scheduled'
                WHEN status = 'revisited_scheduled' THEN 'site_visit_scheduled'
                WHEN status = 'visit_done' THEN 'site_visit_completed'
                WHEN status = 'revisited_completed' THEN 'site_visit_completed'
                WHEN status = 'meeting_scheduled' THEN 'qualified'
                WHEN status = 'meeting_completed' THEN 'qualified'
                WHEN status = 'connected' THEN 'contacted'
                ELSE status
            END
        ");

        // Revert enum to old values
        DB::statement("
            ALTER TABLE leads 
            MODIFY COLUMN status ENUM(
                'new',
                'contacted',
                'qualified',
                'site_visit_scheduled',
                'site_visit_completed',
                'negotiation',
                'closed_won',
                'closed_lost',
                'on_hold'
            ) DEFAULT 'new'
        ");

        // Remove the auto-update column
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('status_auto_update_enabled');
        });
    }
};
