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
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('is_dead')->default(false)->after('blocked_at');
            $table->text('dead_reason')->nullable()->after('is_dead');
            $table->enum('dead_at_stage', ['meeting', 'site_visit', 'closer'])->nullable()->after('dead_reason');
            $table->timestamp('marked_dead_at')->nullable()->after('dead_at_stage');
            $table->foreignId('marked_dead_by')->nullable()->constrained('users')->onDelete('set null')->after('marked_dead_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['marked_dead_by']);
            $table->dropColumn([
                'is_dead',
                'dead_reason',
                'dead_at_stage',
                'marked_dead_at',
                'marked_dead_by',
            ]);
        });
    }
};
