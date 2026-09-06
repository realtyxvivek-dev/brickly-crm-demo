<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE lead_assignments
            MODIFY assignment_method ENUM(
                'manual',
                'round_robin',
                'first_available',
                'percentage',
                'linked_telecaller',
                'rule_based',
                'single_user',
                'cnp_auto_transfer'
            ) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE lead_assignments
            MODIFY assignment_method ENUM(
                'manual',
                'round_robin',
                'first_available',
                'percentage',
                'linked_telecaller',
                'rule_based'
            ) NULL
        ");
    }
};
