<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_proposals', 'lead_capture_mode')) {
                $table->string('lead_capture_mode', 30)->default('off')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_proposals', function (Blueprint $table) {
            if (Schema::hasColumn('lead_proposals', 'lead_capture_mode')) {
                $table->dropColumn('lead_capture_mode');
            }
        });
    }
};
