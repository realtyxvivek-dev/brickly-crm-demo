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
        Schema::table('meetings', function (Blueprint $table) {
            $table->json('completion_proof_photos')->nullable()->after('photos');
            $table->boolean('is_dead')->default(false)->after('rejection_reason');
            $table->text('dead_reason')->nullable()->after('is_dead');
            $table->timestamp('marked_dead_at')->nullable()->after('dead_reason');
            $table->foreignId('marked_dead_by')->nullable()->constrained('users')->onDelete('set null')->after('marked_dead_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['marked_dead_by']);
            $table->dropColumn([
                'completion_proof_photos',
                'is_dead',
                'dead_reason',
                'marked_dead_at',
                'marked_dead_by',
            ]);
        });
    }
};
