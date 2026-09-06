<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'booking_document_reviews')) {
                $table->json('booking_document_reviews')->nullable()->after('booking_activity_log');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (Schema::hasColumn('site_visits', 'booking_document_reviews')) {
                $table->dropColumn('booking_document_reviews');
            }
        });
    }
};
