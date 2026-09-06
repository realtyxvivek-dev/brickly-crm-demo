<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_page_events', function (Blueprint $table) {
            $table->unsignedInteger('duration_ms')->default(0)->after('session_id');
            $table->string('ip_hash', 64)->nullable()->after('meta');
            $table->text('user_agent')->nullable()->after('ip_hash');

            $table->index(['project_share_link_id', 'session_id'], 'ppe_share_session_idx');
            $table->index(['event_name', 'occurred_at'], 'ppe_event_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_page_events', function (Blueprint $table) {
            $table->dropIndex('ppe_share_session_idx');
            $table->dropIndex('ppe_event_occurred_idx');
            $table->dropColumn(['duration_ms', 'ip_hash', 'user_agent']);
        });
    }
};
