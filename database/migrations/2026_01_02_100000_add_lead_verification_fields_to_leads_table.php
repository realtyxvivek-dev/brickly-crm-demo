<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('needs_verification')->default(false)->after('marked_dead_by');
            $table->foreignId('verification_requested_by')->nullable()->after('needs_verification')->constrained('users')->onDelete('set null');
            $table->timestamp('verification_requested_at')->nullable()->after('verification_requested_by');
            $table->foreignId('verified_by')->nullable()->after('verification_requested_at')->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('verification_notes')->nullable()->after('verified_at');
            $table->foreignId('pending_manager_id')->nullable()->after('verification_notes')->constrained('users')->onDelete('set null')->comment('New manager ID pending verification');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['verification_requested_by']);
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['pending_manager_id']);
            $table->dropColumn([
                'needs_verification',
                'verification_requested_by',
                'verification_requested_at',
                'verified_by',
                'verified_at',
                'verification_notes',
                'pending_manager_id',
            ]);
        });
    }
};
