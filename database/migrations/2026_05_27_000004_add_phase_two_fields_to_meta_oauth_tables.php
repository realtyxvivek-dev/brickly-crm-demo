<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_oauth_pages', function (Blueprint $table) {
            $table->string('lead_mode', 30)->default('sandbox')->after('leadgen_subscribed');
            $table->boolean('auto_assign_leads')->default(true)->after('lead_mode');
        });

        Schema::table('meta_oauth_events', function (Blueprint $table) {
            $table->foreignId('crm_lead_id')->nullable()->after('leadgen_id')->constrained('leads')->nullOnDelete();
            $table->json('lead_payload')->nullable()->after('raw_payload');
            $table->json('field_data')->nullable()->after('lead_payload');
            $table->json('mapped_data')->nullable()->after('field_data');
            $table->timestamp('processed_at')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('meta_oauth_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_lead_id');
            $table->dropColumn(['lead_payload', 'field_data', 'mapped_data', 'processed_at']);
        });

        Schema::table('meta_oauth_pages', function (Blueprint $table) {
            $table->dropColumn(['lead_mode', 'auto_assign_leads']);
        });
    }
};
