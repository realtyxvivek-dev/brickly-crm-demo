<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'finance_handover_status')) {
                $table->string('finance_handover_status')->nullable()->after('kyc_last_corrected_at');
            }

            if (!Schema::hasColumn('site_visits', 'finance_transferred_at')) {
                $table->timestamp('finance_transferred_at')->nullable()->after('finance_handover_status');
            }

            if (!Schema::hasColumn('site_visits', 'finance_transferred_by')) {
                $table->unsignedBigInteger('finance_transferred_by')->nullable()->after('finance_transferred_at');
            }

            if (!Schema::hasColumn('site_visits', 'finance_reviewed_at')) {
                $table->timestamp('finance_reviewed_at')->nullable()->after('finance_transferred_by');
            }

            if (!Schema::hasColumn('site_visits', 'finance_reviewed_by')) {
                $table->unsignedBigInteger('finance_reviewed_by')->nullable()->after('finance_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $drops = array_values(array_filter([
                Schema::hasColumn('site_visits', 'finance_reviewed_by') ? 'finance_reviewed_by' : null,
                Schema::hasColumn('site_visits', 'finance_reviewed_at') ? 'finance_reviewed_at' : null,
                Schema::hasColumn('site_visits', 'finance_transferred_by') ? 'finance_transferred_by' : null,
                Schema::hasColumn('site_visits', 'finance_transferred_at') ? 'finance_transferred_at' : null,
                Schema::hasColumn('site_visits', 'finance_handover_status') ? 'finance_handover_status' : null,
            ]));

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
