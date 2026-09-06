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
            $table->foreignId('converted_to_site_visit_id')->nullable()->after('status')->constrained('site_visits')->onDelete('set null');
            $table->boolean('is_converted')->default(false)->after('converted_to_site_visit_id');
            $table->index('is_converted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['converted_to_site_visit_id']);
            $table->dropColumn(['converted_to_site_visit_id', 'is_converted']);
        });
    }
};
