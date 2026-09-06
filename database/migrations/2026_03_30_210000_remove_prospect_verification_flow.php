<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('prospects')
            ->whereIn('verification_status', ['pending', 'pending_verification', 'rejected'])
            ->update([
                'verification_status' => 'verified',
                'verified_at' => $now,
                'verified_by' => DB::raw('COALESCE(verified_by, assigned_manager, manager_id, created_by)'),
                'rejection_reason' => null,
            ]);

        DB::table('tasks')
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
            ->where(function ($query) {
                $query->where('title', 'like', '%prospect verification%')
                    ->orWhere('description', 'like', '%prospect verification%');
            })
            ->update([
                'status' => 'completed',
                'completed_at' => $now,
                'outcome' => 'verified',
                'outcome_recorded_at' => $now,
                'outcome_remark' => 'Prospect verification flow removed; task auto-closed.',
            ]);
    }

    public function down(): void
    {
        // One-way data normalization.
    }
};
