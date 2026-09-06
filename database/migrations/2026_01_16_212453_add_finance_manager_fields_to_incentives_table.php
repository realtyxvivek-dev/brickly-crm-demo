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
        Schema::table('incentives', function (Blueprint $table) {
            // Add Finance Manager verification fields
            $table->foreignId('finance_manager_verified_by')->nullable()->after('crm_verified_at')->constrained('users')->onDelete('set null');
            $table->timestamp('finance_manager_verified_at')->nullable()->after('finance_manager_verified_by');
        });

        // Update enum to include pending_finance_manager
        // MySQL doesn't support direct enum modification, so we use raw SQL
        DB::statement("ALTER TABLE incentives MODIFY COLUMN status ENUM('pending_sales_head', 'pending_crm', 'pending_finance_manager', 'verified', 'rejected') DEFAULT 'pending_finance_manager'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->dropForeign(['finance_manager_verified_by']);
            $table->dropColumn(['finance_manager_verified_by', 'finance_manager_verified_at']);
        });

        // Revert enum (keep original values)
        DB::statement("ALTER TABLE incentives MODIFY COLUMN status ENUM('pending_sales_head', 'pending_crm', 'verified', 'rejected') DEFAULT 'pending_sales_head'");
    }
};
