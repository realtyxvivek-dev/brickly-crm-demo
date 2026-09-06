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
        Schema::table('site_visits', function (Blueprint $table) {
            $table->enum('closer_status', ['pending', 'verified', 'rejected'])->nullable()->after('verification_status');
            $table->timestamp('converted_to_closer_at')->nullable()->after('closer_status');
            $table->foreignId('closer_verified_by')->nullable()->constrained('users')->onDelete('set null')->after('converted_to_closer_at');
            $table->timestamp('closer_verified_at')->nullable()->after('closer_verified_by');
            $table->text('closer_rejection_reason')->nullable()->after('closer_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropForeign(['closer_verified_by']);
            $table->dropColumn([
                'closer_status',
                'converted_to_closer_at',
                'closer_verified_by',
                'closer_verified_at',
                'closer_rejection_reason',
            ]);
        });
    }
};
