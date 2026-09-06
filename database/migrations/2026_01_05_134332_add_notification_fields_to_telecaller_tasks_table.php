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
        Schema::table('telecaller_tasks', function (Blueprint $table) {
            $table->dateTime('notification_sent_at')->nullable()->after('scheduled_at');
            $table->dateTime('moved_to_pending_at')->nullable()->after('notification_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telecaller_tasks', function (Blueprint $table) {
            $table->dropColumn(['notification_sent_at', 'moved_to_pending_at']);
        });
    }
};
