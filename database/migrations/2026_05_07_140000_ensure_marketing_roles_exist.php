<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'name' => 'Marketing Manager',
                'slug' => 'marketing_manager',
                'description' => 'Manage marketing execution work, assign internal tasks, and monitor team delivery',
            ],
            [
                'name' => 'Marketing Executive',
                'slug' => 'marketing_executive',
                'description' => 'Handle assigned marketing execution work and update task progress',
            ],
        ];

        foreach ($roles as $role) {
            $existingRole = DB::table('roles')->where('slug', $role['slug'])->first();

            if ($existingRole) {
                DB::table('roles')->where('id', $existingRole->id)->update([
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('roles')->insert([
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep roles in place to avoid breaking existing users.
    }
};
