<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('follow_ups')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                if (!Schema::hasColumn('follow_ups', 'first_reminder_sent_at')) {
                    $table->timestamp('first_reminder_sent_at')->nullable()->after('reminder_sent_at');
                }
                if (!Schema::hasColumn('follow_ups', 'final_reminder_sent_at')) {
                    $table->timestamp('final_reminder_sent_at')->nullable()->after('first_reminder_sent_at');
                }
            });
        }

        if (Schema::hasTable('meetings')) {
            Schema::table('meetings', function (Blueprint $table) {
                if (!Schema::hasColumn('meetings', 'first_reminder_sent_at')) {
                    $table->timestamp('first_reminder_sent_at')->nullable()->after('reminder_sent_at');
                }
                if (!Schema::hasColumn('meetings', 'final_reminder_sent_at')) {
                    $table->timestamp('final_reminder_sent_at')->nullable()->after('first_reminder_sent_at');
                }
            });
        }

        if (Schema::hasTable('site_visits')) {
            Schema::table('site_visits', function (Blueprint $table) {
                if (!Schema::hasColumn('site_visits', 'first_reminder_sent_at')) {
                    $table->timestamp('first_reminder_sent_at')->nullable()->after('scheduled_at');
                }
                if (!Schema::hasColumn('site_visits', 'final_reminder_sent_at')) {
                    $table->timestamp('final_reminder_sent_at')->nullable()->after('first_reminder_sent_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('follow_ups')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                $table->dropColumn(['first_reminder_sent_at', 'final_reminder_sent_at']);
            });
        }

        if (Schema::hasTable('meetings')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropColumn(['first_reminder_sent_at', 'final_reminder_sent_at']);
            });
        }

        if (Schema::hasTable('site_visits')) {
            Schema::table('site_visits', function (Blueprint $table) {
                $table->dropColumn(['first_reminder_sent_at', 'final_reminder_sent_at']);
            });
        }
    }
};
