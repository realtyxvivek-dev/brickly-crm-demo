<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'booking_form_version')) {
                $table->string('booking_form_version', 20)->nullable()->after('kyc_dynamic_form_id');
            }

            if (!Schema::hasColumn('site_visits', 'booking_lifecycle_status')) {
                $table->string('booking_lifecycle_status', 50)->nullable()->after('booking_form_version');
            }

            if (!Schema::hasColumn('site_visits', 'booking_payment_proofs')) {
                $table->json('booking_payment_proofs')->nullable()->after('booking_lifecycle_status');
            }

            if (!Schema::hasColumn('site_visits', 'booking_activity_log')) {
                $table->json('booking_activity_log')->nullable()->after('booking_payment_proofs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            foreach (['booking_activity_log', 'booking_payment_proofs', 'booking_lifecycle_status', 'booking_form_version'] as $column) {
                if (Schema::hasColumn('site_visits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
