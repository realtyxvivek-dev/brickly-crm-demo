<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_lead_id')->nullable()->after('normalized_phone');
            $table->timestamp('merged_at')->nullable()->after('merged_into_lead_id');
            $table->string('merge_reason')->nullable()->after('merged_at');

            $table->index('merged_into_lead_id', 'leads_merged_into_lead_id_index');
        });

        Schema::create('lead_merge_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('master_lead_id');
            $table->unsignedBigInteger('duplicate_lead_id');
            $table->string('normalized_phone', 20)->nullable();
            $table->string('status', 30)->default('processing');
            $table->json('master_snapshot')->nullable();
            $table->json('duplicate_snapshot')->nullable();
            $table->json('moved_record_counts')->nullable();
            $table->text('remark')->nullable();
            $table->text('failure_details')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->unique('duplicate_lead_id', 'lead_merge_audits_duplicate_unique');
            $table->index(['master_lead_id', 'created_at'], 'lead_merge_audits_master_created_index');
            $table->index(['status', 'created_at'], 'lead_merge_audits_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_merge_audits');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_merged_into_lead_id_index');
            $table->dropColumn(['merged_into_lead_id', 'merged_at', 'merge_reason']);
        });
    }
};
