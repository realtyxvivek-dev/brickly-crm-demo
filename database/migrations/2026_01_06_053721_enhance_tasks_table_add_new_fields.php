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
        Schema::table('tasks', function (Blueprint $table) {
            // Add priority field
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('status');
            
            // Add due_date (separate from scheduled_at)
            $table->dateTime('due_date')->nullable()->after('scheduled_at');
            
            // Add notes field
            $table->text('notes')->nullable()->after('description');
            
            // Add recurrence fields for recurring tasks
            $table->json('recurrence_pattern')->nullable()->after('due_date');
            $table->dateTime('recurrence_end_date')->nullable()->after('recurrence_pattern');
            
            // Add reschedule history
            $table->dateTime('rescheduled_from')->nullable()->after('recurrence_end_date');
            
            // Add indexes for new fields
            $table->index('priority');
            $table->index('due_date');
            $table->index(['priority', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['priority', 'due_date']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['priority']);
            
            $table->dropColumn([
                'priority',
                'due_date',
                'notes',
                'recurrence_pattern',
                'recurrence_end_date',
                'rescheduled_from',
            ]);
        });
    }
};
