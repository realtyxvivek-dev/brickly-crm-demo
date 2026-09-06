<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('employee_departments')) {
            return;
        }

        $now = Carbon::now();

        $departments = [
            ['name' => 'Sales', 'code' => 'SALES', 'display_order' => 1],
            ['name' => 'Marketing', 'code' => 'MARKETING', 'display_order' => 2],
            ['name' => 'HR', 'code' => 'HR', 'display_order' => 3],
            ['name' => 'Finance', 'code' => 'FINANCE', 'display_order' => 4],
        ];

        foreach ($departments as $department) {
            $existing = DB::table('employee_departments')
                ->where('name', $department['name'])
                ->orWhere('code', $department['code'])
                ->first();

            if ($existing) {
                DB::table('employee_departments')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $department['name'],
                        'code' => $department['code'],
                        'is_active' => true,
                        'display_order' => $department['display_order'],
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('employee_departments')->insert([
                'name' => $department['name'],
                'code' => $department['code'],
                'is_active' => true,
                'display_order' => $department['display_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('employee_departments')) {
            return;
        }

        DB::table('employee_departments')
            ->whereIn('code', ['SALES', 'MARKETING', 'HR', 'FINANCE'])
            ->delete();
    }
};
