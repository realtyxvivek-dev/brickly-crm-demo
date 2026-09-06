<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_entries', 'deleted_by')) {
                $table->foreignId('deleted_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('expense_entries', 'delete_reason')) {
                $table->text('delete_reason')->nullable()->after('deleted_by');
            }

            if (!Schema::hasColumn('expense_entries', 'deleted_at')) {
                $table->softDeletes()->after('delete_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_entries', function (Blueprint $table) {
            if (Schema::hasColumn('expense_entries', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }

            if (Schema::hasColumn('expense_entries', 'delete_reason')) {
                $table->dropColumn('delete_reason');
            }

            if (Schema::hasColumn('expense_entries', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
