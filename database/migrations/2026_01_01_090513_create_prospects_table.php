<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('prospects')) {
            Schema::create('prospects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
                $table->foreignId('telecaller_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('restrict');
                $table->string('customer_name');
                $table->string('phone');
                $table->string('budget')->nullable();
                $table->string('preferred_location')->nullable();
                $table->string('size')->nullable();
                $table->enum('purpose', ['end_user', 'investment'])->default('end_user');
                $table->string('possession')->nullable();
                $table->text('remark')->nullable();
                $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
                $table->datetime('verified_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->index(['telecaller_id', 'verification_status']);
                $table->index(['manager_id', 'verification_status']);
                $table->index('lead_id');
            });
        } else {
            // Add missing columns if table exists
            Schema::table('prospects', function (Blueprint $table) {
                if (!Schema::hasColumn('prospects', 'telecaller_id')) {
                    $table->foreignId('telecaller_id')->nullable()->after('lead_id')->constrained('users')->onDelete('cascade');
                }
                if (!Schema::hasColumn('prospects', 'manager_id')) {
                    $table->foreignId('manager_id')->nullable()->after('telecaller_id')->constrained('users')->onDelete('restrict');
                }
                if (!Schema::hasColumn('prospects', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('verified_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop table if it existed before
        if (Schema::hasTable('prospects')) {
            Schema::table('prospects', function (Blueprint $table) {
                if (Schema::hasColumn('prospects', 'telecaller_id')) {
                    $table->dropForeign(['telecaller_id']);
                    $table->dropColumn('telecaller_id');
                }
                if (Schema::hasColumn('prospects', 'manager_id')) {
                    $table->dropForeign(['manager_id']);
                    $table->dropColumn('manager_id');
                }
                if (Schema::hasColumn('prospects', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
            });
        }
    }
};
