<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE leads
            MODIFY COLUMN hiring_status ENUM(
                'new',
                'connected',
                'contacted',
                'interview_pending',
                'interview_scheduled',
                'interview_complete',
                'interview_done',
                'selected',
                'shortlisted',
                'offer_sent',
                'hired',
                'rejected',
                'not_interested',
                'not_reachable',
                'wrong_number',
                'duplicate',
                'on_hold'
            ) NULL
        ");

        DB::table('leads')->where('hiring_status', 'connected')->update(['hiring_status' => 'contacted']);
        DB::table('leads')->where('hiring_status', 'interview_pending')->update(['hiring_status' => 'interview_scheduled']);
        DB::table('leads')->where('hiring_status', 'interview_complete')->update(['hiring_status' => 'interview_done']);
        DB::table('leads')->where('hiring_status', 'selected')->update(['hiring_status' => 'hired']);

        DB::statement("
            ALTER TABLE leads
            MODIFY COLUMN hiring_status ENUM(
                'new',
                'contacted',
                'interview_scheduled',
                'interview_done',
                'shortlisted',
                'offer_sent',
                'hired',
                'rejected',
                'not_interested',
                'not_reachable',
                'wrong_number',
                'duplicate',
                'on_hold'
            ) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE leads
            MODIFY COLUMN hiring_status ENUM(
                'new',
                'connected',
                'contacted',
                'interview_pending',
                'interview_scheduled',
                'interview_complete',
                'interview_done',
                'selected',
                'shortlisted',
                'offer_sent',
                'hired',
                'rejected',
                'not_interested',
                'not_reachable',
                'wrong_number',
                'duplicate',
                'on_hold'
            ) NULL
        ");

        DB::table('leads')->where('hiring_status', 'contacted')->update(['hiring_status' => 'connected']);
        DB::table('leads')->where('hiring_status', 'interview_scheduled')->update(['hiring_status' => 'interview_pending']);
        DB::table('leads')->where('hiring_status', 'interview_done')->update(['hiring_status' => 'interview_complete']);
        DB::table('leads')->whereIn('hiring_status', ['shortlisted', 'offer_sent', 'hired'])->update(['hiring_status' => 'selected']);
        DB::table('leads')->whereIn('hiring_status', ['not_interested', 'not_reachable', 'wrong_number', 'duplicate', 'on_hold'])->update(['hiring_status' => 'rejected']);

        DB::statement("
            ALTER TABLE leads
            MODIFY COLUMN hiring_status ENUM(
                'new',
                'connected',
                'interview_pending',
                'interview_complete',
                'selected',
                'rejected'
            ) NULL
        ");
    }
};
