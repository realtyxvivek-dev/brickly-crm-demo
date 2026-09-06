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
        Schema::table('site_visits', function (Blueprint $table) {
            $table->enum('visit_type', ['with_family', 'without_family'])->nullable()->after('visit_notes');
            $table->boolean('reminder_enabled')->default(false)->after('visit_type');
            $table->integer('reminder_minutes')->nullable()->default(10)->after('reminder_enabled');
            $table->foreignId('reminder_task_id')->nullable()->after('reminder_minutes')->constrained('tasks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropForeign(['reminder_task_id']);
            $table->dropColumn(['visit_type', 'reminder_enabled', 'reminder_minutes', 'reminder_task_id']);
        });
    }
};
