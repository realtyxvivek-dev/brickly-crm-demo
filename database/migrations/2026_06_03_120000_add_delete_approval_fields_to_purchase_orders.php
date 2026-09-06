<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('delete_requested_by')->nullable()->after('expense_entry_id')->constrained('users')->nullOnDelete();
            $table->timestamp('delete_requested_at')->nullable()->after('delete_requested_by');
            $table->text('delete_request_reason')->nullable()->after('delete_requested_at');
            $table->string('delete_restore_status')->nullable()->after('delete_request_reason');
            $table->foreignId('delete_reviewed_by')->nullable()->after('delete_restore_status')->constrained('users')->nullOnDelete();
            $table->timestamp('delete_reviewed_at')->nullable()->after('delete_reviewed_by');
            $table->text('delete_reject_reason')->nullable()->after('delete_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delete_requested_by');
            $table->dropColumn([
                'delete_requested_at',
                'delete_request_reason',
                'delete_restore_status',
            ]);
            $table->dropConstrainedForeignId('delete_reviewed_by');
            $table->dropColumn([
                'delete_reviewed_at',
                'delete_reject_reason',
            ]);
        });
    }
};
