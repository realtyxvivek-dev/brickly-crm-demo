<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'revenue_value')) {
                $table->decimal('revenue_value', 15, 2)->nullable()->after('finance_reviewed_by');
            }

            if (!Schema::hasColumn('site_visits', 'revenue_note')) {
                $table->text('revenue_note')->nullable()->after('revenue_value');
            }

            if (!Schema::hasColumn('site_visits', 'revenue_entered_by')) {
                $table->unsignedBigInteger('revenue_entered_by')->nullable()->after('revenue_note');
            }

            if (!Schema::hasColumn('site_visits', 'revenue_entered_at')) {
                $table->timestamp('revenue_entered_at')->nullable()->after('revenue_entered_by');
            }

            if (!Schema::hasColumn('site_visits', 'revenue_updated_by')) {
                $table->unsignedBigInteger('revenue_updated_by')->nullable()->after('revenue_entered_at');
            }

            if (!Schema::hasColumn('site_visits', 'revenue_updated_at')) {
                $table->timestamp('revenue_updated_at')->nullable()->after('revenue_updated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $drops = array_values(array_filter([
                Schema::hasColumn('site_visits', 'revenue_updated_at') ? 'revenue_updated_at' : null,
                Schema::hasColumn('site_visits', 'revenue_updated_by') ? 'revenue_updated_by' : null,
                Schema::hasColumn('site_visits', 'revenue_entered_at') ? 'revenue_entered_at' : null,
                Schema::hasColumn('site_visits', 'revenue_entered_by') ? 'revenue_entered_by' : null,
                Schema::hasColumn('site_visits', 'revenue_note') ? 'revenue_note' : null,
                Schema::hasColumn('site_visits', 'revenue_value') ? 'revenue_value' : null,
            ]));

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
