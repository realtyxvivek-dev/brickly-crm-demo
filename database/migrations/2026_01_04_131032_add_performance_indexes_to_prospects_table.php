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
        if (!Schema::hasTable('prospects')) {
            return;
        }
        
        // Check and add index on created_at
        $indexes = DB::select("SHOW INDEXES FROM prospects WHERE Key_name LIKE '%created_at%'");
        if (empty($indexes)) {
            DB::statement('ALTER TABLE prospects ADD INDEX prospects_created_at_index (created_at)');
        }
        
        // Check and add composite index on created_by and verification_status
        $compositeIndexes = DB::select("SHOW INDEXES FROM prospects WHERE Key_name LIKE '%created_by%verification_status%' OR (Column_name = 'created_by' AND Seq_in_index = 1)");
        $hasComposite = false;
        foreach ($compositeIndexes as $idx) {
            $allIndexes = DB::select("SHOW INDEXES FROM prospects WHERE Key_name = ?", [$idx->Key_name]);
            $columns = array_column($allIndexes, 'Column_name');
            if (in_array('created_by', $columns) && in_array('verification_status', $columns)) {
                $hasComposite = true;
                break;
            }
        }
        if (!$hasComposite) {
            DB::statement('ALTER TABLE prospects ADD INDEX prospects_created_by_verification_status_index (created_by, verification_status)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('prospects')) {
            return;
        }
        
        Schema::table('prospects', function (Blueprint $table) {
            try {
                $table->dropIndex(['created_at']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            try {
                $table->dropIndex(['created_by', 'verification_status']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            try {
                $table->dropIndex(['verification_status']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
        });
    }
};
