<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('roles')->where('slug', 'junior_hr')->first();

        if ($existing) {
            DB::table('roles')
                ->where('id', $existing->id)
                ->update([
                    'name' => 'Junior HR',
                    'description' => 'Handle assigned hiring candidates only',
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('roles')->insert([
            'name' => 'Junior HR',
            'slug' => 'junior_hr',
            'description' => 'Handle assigned hiring candidates only',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Keep the role in place so existing users are not orphaned on rollback.
    }
};
