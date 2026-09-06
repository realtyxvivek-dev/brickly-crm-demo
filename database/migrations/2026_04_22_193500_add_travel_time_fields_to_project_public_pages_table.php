<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_public_pages', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location_summary');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('map_zoom')->nullable()->after('longitude');
            $table->json('popular_origins')->nullable()->after('map_zoom');
        });
    }

    public function down(): void
    {
        Schema::table('project_public_pages', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'map_zoom', 'popular_origins']);
        });
    }
};
