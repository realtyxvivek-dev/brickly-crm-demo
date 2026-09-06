<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tasksQueueHiddenAfter = Schema::hasColumn('tasks', 'deleted_at') ? 'deleted_at' : 'follow_up_id';
        $telecallerQueueHiddenAfter = Schema::hasColumn('telecaller_tasks', 'deleted_at') ? 'deleted_at' : 'follow_up_id';

        Schema::table('tasks', function (Blueprint $table) use ($tasksQueueHiddenAfter) {
            $table->unsignedBigInteger('meeting_id')->nullable()->after('lead_id');
            $table->unsignedBigInteger('site_visit_id')->nullable()->after('meeting_id');
            $table->unsignedBigInteger('follow_up_id')->nullable()->after('site_visit_id');
            $table->timestamp('queue_hidden_at')->nullable()->after($tasksQueueHiddenAfter);
            $table->string('queue_hidden_reason')->nullable()->after('queue_hidden_at');
        });

        Schema::table('telecaller_tasks', function (Blueprint $table) use ($telecallerQueueHiddenAfter) {
            $table->unsignedBigInteger('site_visit_id')->nullable()->after('meeting_id');
            $table->unsignedBigInteger('follow_up_id')->nullable()->after('site_visit_id');
            $table->timestamp('queue_hidden_at')->nullable()->after($telecallerQueueHiddenAfter);
            $table->string('queue_hidden_reason')->nullable()->after('queue_hidden_at');
        });
    }

    public function down(): void
    {
        Schema::table('telecaller_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'site_visit_id',
                'follow_up_id',
                'queue_hidden_at',
                'queue_hidden_reason',
            ]);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'meeting_id',
                'site_visit_id',
                'follow_up_id',
                'queue_hidden_at',
                'queue_hidden_reason',
            ]);
        });
    }
};
