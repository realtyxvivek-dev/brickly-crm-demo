<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->unsignedInteger('resubmission_count')->default(0)->after('rejection_reason');
            $table->dateTime('resubmitted_at')->nullable()->after('resubmission_count');
            $table->dateTime('latest_rejected_at')->nullable()->after('resubmitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropColumn([
                'resubmission_count',
                'resubmitted_at',
                'latest_rejected_at',
            ]);
        });
    }
};
