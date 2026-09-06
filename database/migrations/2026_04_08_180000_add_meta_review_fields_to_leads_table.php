<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('meta_stage')->nullable()->after('last_reenquiry_fb_form_id');
            $table->timestamp('meta_stage_updated_at')->nullable()->after('meta_stage');
            $table->unsignedBigInteger('meta_stage_updated_by')->nullable()->after('meta_stage_updated_at');
            $table->text('meta_review_note')->nullable()->after('meta_stage_updated_by');
            $table->string('meta_sync_status')->nullable()->after('meta_review_note');
            $table->timestamp('meta_last_synced_at')->nullable()->after('meta_sync_status');
            $table->text('meta_last_sync_error')->nullable()->after('meta_last_synced_at');
            $table->string('last_sent_meta_stage')->nullable()->after('meta_last_sync_error');

            $table->index('meta_stage');
            $table->index('meta_sync_status');
            $table->foreign('meta_stage_updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['meta_stage_updated_by']);
            $table->dropIndex(['meta_stage']);
            $table->dropIndex(['meta_sync_status']);
            $table->dropColumn([
                'meta_stage',
                'meta_stage_updated_at',
                'meta_stage_updated_by',
                'meta_review_note',
                'meta_sync_status',
                'meta_last_synced_at',
                'meta_last_sync_error',
                'last_sent_meta_stage',
            ]);
        });
    }
};
