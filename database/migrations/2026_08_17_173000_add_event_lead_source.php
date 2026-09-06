<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        $nextSortOrder = ((int) DB::table('lead_sources')->max('sort_order')) + 10;

        $existing = DB::table('lead_sources')->where('key', 'event')->exists();

        if ($existing) {
            DB::table('lead_sources')->where('key', 'event')->update([
                'name' => 'Event',
                'type' => 'offline',
                'is_active' => true,
                'is_system' => false,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('lead_sources')->insert([
                'name' => 'Event',
                'key' => 'event',
                'type' => 'offline',
                'is_active' => true,
                'is_system' => false,
                'sort_order' => $nextSortOrder,
                'updated_at' => now(),
                'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        DB::table('lead_sources')
            ->where('key', 'event')
            ->where('is_system', false)
            ->delete();
    }
};
