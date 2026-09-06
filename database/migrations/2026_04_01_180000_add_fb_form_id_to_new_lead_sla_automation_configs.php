<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_lead_sla_automation_configs', function (Blueprint $table) {
            $table->dropUnique(['source']);
            $table->foreignId('fb_form_id')->nullable()->after('source')->constrained('fb_forms')->nullOnDelete();
            $table->unique(['source', 'fb_form_id'], 'new_lead_sla_source_form_unique');
        });
    }

    public function down(): void
    {
        Schema::table('new_lead_sla_automation_configs', function (Blueprint $table) {
            $table->dropUnique('new_lead_sla_source_form_unique');
            $table->dropConstrainedForeignId('fb_form_id');
            $table->unique('source');
        });
    }
};
