<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'actual_closer_date')) {
                $table->date('actual_closer_date')->nullable()->after('closer_submitted_by');
            }

            if (!Schema::hasColumn('site_visits', 'actual_closer_backdate_reason')) {
                $table->text('actual_closer_backdate_reason')->nullable()->after('actual_closer_date');
            }

            if (!Schema::hasColumn('site_visits', 'actual_closer_date_approved_at')) {
                $table->timestamp('actual_closer_date_approved_at')->nullable()->after('actual_closer_backdate_reason');
            }

            if (!Schema::hasColumn('site_visits', 'actual_closer_date_approved_by')) {
                $table->foreignId('actual_closer_date_approved_by')->nullable()->after('actual_closer_date_approved_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (Schema::hasColumn('site_visits', 'actual_closer_date_approved_by')) {
                $table->dropForeign(['actual_closer_date_approved_by']);
                $table->dropColumn('actual_closer_date_approved_by');
            }

            foreach (['actual_closer_date_approved_at', 'actual_closer_backdate_reason', 'actual_closer_date'] as $column) {
                if (Schema::hasColumn('site_visits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
