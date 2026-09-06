<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dateTime('reminder_sent_at')->nullable()->after('scheduled_at');
            $table->dateTime('overdue_notified_at')->nullable()->after('reminder_sent_at');
            $table->index('reminder_sent_at');
            $table->index('overdue_notified_at');
        });

        Schema::table('telecaller_tasks', function (Blueprint $table) {
            $table->dateTime('overdue_notified_at')->nullable()->after('notification_sent_at');
            $table->index('overdue_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex(['reminder_sent_at']);
            $table->dropIndex(['overdue_notified_at']);
            $table->dropColumn(['reminder_sent_at', 'overdue_notified_at']);
        });

        Schema::table('telecaller_tasks', function (Blueprint $table) {
            $table->dropIndex(['overdue_notified_at']);
            $table->dropColumn('overdue_notified_at');
        });
    }
};
