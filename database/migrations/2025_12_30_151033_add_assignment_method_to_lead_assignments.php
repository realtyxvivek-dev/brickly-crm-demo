<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->enum('assignment_method', ['manual', 'round_robin', 'first_available', 'percentage', 'linked_telecaller'])->nullable()->after('assignment_type');
            $table->foreignId('sheet_assignment_config_id')->nullable()->after('sheet_config_id')->constrained('sheet_assignment_config')->onDelete('set null');
            
            $table->index('assignment_method');
            $table->index('sheet_assignment_config_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->dropForeign(['sheet_assignment_config_id']);
            $table->dropColumn(['assignment_method', 'sheet_assignment_config_id']);
        });
    }
};
