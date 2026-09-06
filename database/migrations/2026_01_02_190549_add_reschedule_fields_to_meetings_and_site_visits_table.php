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
        Schema::table('meetings', function (Blueprint $table) {
            $table->dateTime('rescheduled_at')->nullable()->after('marked_dead_by');
            $table->foreignId('rescheduled_by')->nullable()->after('rescheduled_at')->constrained('users')->onDelete('set null');
            $table->text('reschedule_reason')->nullable()->after('rescheduled_by');
            $table->integer('reschedule_count')->default(0)->after('reschedule_reason');
            $table->boolean('is_rescheduled')->default(false)->after('reschedule_count');
        });

        Schema::table('site_visits', function (Blueprint $table) {
            $table->dateTime('rescheduled_at')->nullable()->after('marked_dead_by');
            $table->foreignId('rescheduled_by')->nullable()->after('rescheduled_at')->constrained('users')->onDelete('set null');
            $table->text('reschedule_reason')->nullable()->after('rescheduled_by');
            $table->integer('reschedule_count')->default(0)->after('reschedule_reason');
            $table->boolean('is_rescheduled')->default(false)->after('reschedule_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_by']);
            $table->dropColumn(['rescheduled_at', 'rescheduled_by', 'reschedule_reason', 'reschedule_count', 'is_rescheduled']);
        });

        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_by']);
            $table->dropColumn(['rescheduled_at', 'rescheduled_by', 'reschedule_reason', 'reschedule_count', 'is_rescheduled']);
        });
    }
};
