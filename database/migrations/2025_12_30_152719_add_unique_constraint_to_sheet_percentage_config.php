<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sheet_percentage_config')) {
            // Check if the unique constraint already exists using raw SQL
            $constraintExists = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.table_constraints 
                WHERE constraint_schema = DATABASE() 
                AND table_name = 'sheet_percentage_config' 
                AND constraint_name = 'spc_sac_id_user_id_unique'
            ");
            
            if ($constraintExists[0]->count == 0) {
                DB::statement('ALTER TABLE `sheet_percentage_config` ADD UNIQUE `spc_sac_id_user_id_unique` (`sheet_assignment_config_id`, `user_id`)');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sheet_percentage_config')) {
            Schema::table('sheet_percentage_config', function (Blueprint $table) {
                $table->dropUnique('spc_sac_id_user_id_unique');
            });
        }
    }
};
