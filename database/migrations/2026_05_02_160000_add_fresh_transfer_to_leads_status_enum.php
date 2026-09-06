<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE leads
            MODIFY COLUMN status ENUM(
                'new',
                'fresh_transfer',
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
            SET status = 'connected'
            WHERE status = 'fresh_transfer'
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
                'not_interested',
                'on_hold'
            ) DEFAULT 'new'
        ");
    }
};
