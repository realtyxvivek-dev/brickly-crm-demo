<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('crm_lead_id')->nullable()->after('fb_form_id');
            $table->foreign('crm_lead_id')->references('id')->on('leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fb_leads', function (Blueprint $table) {
            $table->dropForeign(['crm_lead_id']);
            $table->dropColumn('crm_lead_id');
        });
    }
};
