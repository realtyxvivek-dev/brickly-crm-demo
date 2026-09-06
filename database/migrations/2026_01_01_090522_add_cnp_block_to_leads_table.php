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
            $table->integer('cnp_count')->default(0)->after('next_followup_at');
            $table->boolean('is_blocked')->default(false)->after('cnp_count');
            $table->text('blocked_reason')->nullable()->after('is_blocked');
            $table->datetime('blocked_at')->nullable()->after('blocked_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['cnp_count', 'is_blocked', 'blocked_reason', 'blocked_at']);
        });
    }
};
