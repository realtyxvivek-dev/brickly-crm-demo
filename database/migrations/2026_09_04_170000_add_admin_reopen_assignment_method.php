<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        $column = DB::selectOne("SHOW COLUMNS FROM lead_assignments WHERE Field = 'assignment_method'");
        if (!str_starts_with($column->Type, 'enum(') || str_contains($column->Type, "'admin_reopen'")) return;
        $type = substr($column->Type, 0, -1).",'admin_reopen')";
        DB::statement("ALTER TABLE lead_assignments MODIFY assignment_method $type NULL");
    }

    public function down(): void
    {
        // Keep the additive enum value so existing reopen history remains valid on code rollback.
    }
};
