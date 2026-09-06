<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $followUpsQueueHiddenAfter = Schema::hasColumn('follow_ups', 'deleted_at') ? 'deleted_at' : 'updated_at';
        $meetingsQueueHiddenAfter = Schema::hasColumn('meetings', 'deleted_at') ? 'deleted_at' : 'updated_at';
        $siteVisitsQueueHiddenAfter = Schema::hasColumn('site_visits', 'deleted_at') ? 'deleted_at' : 'updated_at';

        Schema::table('follow_ups', function (Blueprint $table) use ($followUpsQueueHiddenAfter) {
            if (!Schema::hasColumn('follow_ups', 'queue_hidden_at')) {
                $table->timestamp('queue_hidden_at')->nullable()->after($followUpsQueueHiddenAfter);
            }

            if (!Schema::hasColumn('follow_ups', 'queue_hidden_reason')) {
                $table->string('queue_hidden_reason')->nullable()->after('queue_hidden_at');
            }
        });

        Schema::table('meetings', function (Blueprint $table) use ($meetingsQueueHiddenAfter) {
            if (!Schema::hasColumn('meetings', 'queue_hidden_at')) {
                $table->timestamp('queue_hidden_at')->nullable()->after($meetingsQueueHiddenAfter);
            }

            if (!Schema::hasColumn('meetings', 'queue_hidden_reason')) {
                $table->string('queue_hidden_reason')->nullable()->after('queue_hidden_at');
            }
        });

        Schema::table('site_visits', function (Blueprint $table) use ($siteVisitsQueueHiddenAfter) {
            if (!Schema::hasColumn('site_visits', 'queue_hidden_at')) {
                $table->timestamp('queue_hidden_at')->nullable()->after($siteVisitsQueueHiddenAfter);
            }

            if (!Schema::hasColumn('site_visits', 'queue_hidden_reason')) {
                $table->string('queue_hidden_reason')->nullable()->after('queue_hidden_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('follow_ups', 'queue_hidden_reason') ? 'queue_hidden_reason' : null,
                Schema::hasColumn('follow_ups', 'queue_hidden_at') ? 'queue_hidden_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('meetings', 'queue_hidden_reason') ? 'queue_hidden_reason' : null,
                Schema::hasColumn('meetings', 'queue_hidden_at') ? 'queue_hidden_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('site_visits', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('site_visits', 'queue_hidden_reason') ? 'queue_hidden_reason' : null,
                Schema::hasColumn('site_visits', 'queue_hidden_at') ? 'queue_hidden_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
