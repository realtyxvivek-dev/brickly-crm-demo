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
        Schema::table('unit_types', function (Blueprint $table) {
            $table->foreignId('tower_id')->nullable()->after('project_id')->constrained('towers')->onDelete('cascade');
            $table->index('tower_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropForeign(['tower_id']);
            $table->dropIndex(['tower_id']);
            $table->dropColumn('tower_id');
        });
    }
};
