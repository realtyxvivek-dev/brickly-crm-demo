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
        Schema::table('targets', function (Blueprint $table) {
            $table->integer('target_prospects_extract')->default(0)->after('target_closers');
            $table->integer('target_prospects_verified')->default(0)->after('target_prospects_extract');
            $table->integer('target_calls')->default(0)->after('target_prospects_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn(['target_prospects_extract', 'target_prospects_verified', 'target_calls']);
        });
    }
};

