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
        Schema::table('call_logs', function (Blueprint $table) {
            // Add user_id for Sales Executive/Manager calls (not just telecaller)
            $table->foreignId('user_id')->nullable()->after('telecaller_id')->constrained('users')->onDelete('cascade');
            
            // Rename notes to call_notes for clarity and make it text for rich text
            // Note: notes already exists, so we'll keep it and add call_notes as alias
            // Actually, notes already exists, so we'll use it as call_notes
            
            // Add recording_url for future call recording link
            $table->string('recording_url')->nullable()->after('notes');
            
            // Add call_outcome enum
            $table->enum('call_outcome', ['interested', 'not_interested', 'callback', 'no_answer', 'busy', 'other'])->nullable()->after('status');
            
            // Add next_followup_date for auto-suggest next followup
            $table->datetime('next_followup_date')->nullable()->after('call_outcome');
            
            // Add is_verified flag
            $table->boolean('is_verified')->default(false)->after('next_followup_date');
            
            // Add synced_from_mobile flag
            $table->boolean('synced_from_mobile')->default(false)->after('is_verified');
            
            // Add indexes
            $table->index(['user_id', 'start_time'], 'call_logs_user_id_start_time_index');
            $table->index(['lead_id', 'start_time'], 'call_logs_lead_id_start_time_index');
            $table->index('call_outcome', 'call_logs_call_outcome_index');
            $table->index('synced_from_mobile', 'call_logs_synced_from_mobile_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('call_logs_user_id_start_time_index');
            $table->dropIndex('call_logs_lead_id_start_time_index');
            $table->dropIndex('call_logs_call_outcome_index');
            $table->dropIndex('call_logs_synced_from_mobile_index');
            
            // Drop foreign key and column
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'recording_url',
                'call_outcome',
                'next_followup_date',
                'is_verified',
                'synced_from_mobile'
            ]);
        });
    }
};
