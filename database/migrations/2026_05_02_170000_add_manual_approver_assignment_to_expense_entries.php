<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_entries', function (Blueprint $table) {
            $table->foreignId('approval_assigned_to')
                ->nullable()
                ->after('updated_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approval_assigned_by')
                ->nullable()
                ->after('approval_assigned_to')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approval_assigned_at')
                ->nullable()
                ->after('approval_assigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('expense_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_assigned_by');
            $table->dropColumn('approval_assigned_at');
            $table->dropConstrainedForeignId('approval_assigned_to');
        });
    }
};
