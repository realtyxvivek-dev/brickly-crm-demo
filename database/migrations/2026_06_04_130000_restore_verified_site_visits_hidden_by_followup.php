<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_visits')
            || ! Schema::hasColumn('site_visits', 'queue_hidden_at')
            || ! Schema::hasColumn('site_visits', 'queue_hidden_reason')) {
            return;
        }

        DB::table('site_visits')
            ->where('status', 'completed')
            ->where('verification_status', 'verified')
            ->where('queue_hidden_reason', 'superseded_by_follow_up')
            ->update([
                'queue_hidden_at' => null,
                'queue_hidden_reason' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data-only repair. Do not re-hide verified visit history on rollback.
    }
};
