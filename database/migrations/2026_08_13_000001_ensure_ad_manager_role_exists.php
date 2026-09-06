<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $payload = [
            'name' => 'Ad Manager',
            'description' => 'Monitor assigned lead sources, view source reports, and raise purchase requests',
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (DB::table('roles')->where('slug', 'ad_manager')->exists()) {
            DB::table('roles')->where('slug', 'ad_manager')->update($payload);
        } else {
            DB::table('roles')->insert($payload + [
                'slug' => 'ad_manager',
                'permissions' => null,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'ad_manager')->delete();
    }
};
