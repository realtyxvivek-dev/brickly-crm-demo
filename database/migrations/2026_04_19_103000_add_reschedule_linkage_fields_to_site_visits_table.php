<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'rescheduled_from_visit_id')) {
                $table->unsignedBigInteger('rescheduled_from_visit_id')->nullable()->after('rescheduled_by');
            }

            if (!Schema::hasColumn('site_visits', 'rescheduled_to_visit_id')) {
                $table->unsignedBigInteger('rescheduled_to_visit_id')->nullable()->after('rescheduled_from_visit_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('site_visits', 'rescheduled_to_visit_id')) {
                $columns[] = 'rescheduled_to_visit_id';
            }

            if (Schema::hasColumn('site_visits', 'rescheduled_from_visit_id')) {
                $columns[] = 'rescheduled_from_visit_id';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
