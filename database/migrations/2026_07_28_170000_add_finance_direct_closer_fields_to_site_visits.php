<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'is_finance_direct_closer')) {
                $table->boolean('is_finance_direct_closer')->default(false);
            }

            if (!Schema::hasColumn('site_visits', 'approval_admin_id')) {
                $table->foreignId('approval_admin_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (Schema::hasColumn('site_visits', 'approval_admin_id')) {
                $table->dropForeign(['approval_admin_id']);
                $table->dropColumn('approval_admin_id');
            }

            if (Schema::hasColumn('site_visits', 'is_finance_direct_closer')) {
                $table->dropColumn('is_finance_direct_closer');
            }
        });
    }
};
