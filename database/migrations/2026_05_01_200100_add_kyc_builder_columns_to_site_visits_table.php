<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'kyc_dynamic_form_id')) {
                $table->foreignId('kyc_dynamic_form_id')->nullable()->after('unit_details')->constrained('dynamic_forms')->nullOnDelete();
            }

            if (!Schema::hasColumn('site_visits', 'kyc_custom_fields')) {
                $table->json('kyc_custom_fields')->nullable()->after('kyc_dynamic_form_id');
            }

            if (!Schema::hasColumn('site_visits', 'kyc_section_remarks')) {
                $table->json('kyc_section_remarks')->nullable()->after('kyc_custom_fields');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (Schema::hasColumn('site_visits', 'kyc_section_remarks')) {
                $table->dropColumn('kyc_section_remarks');
            }

            if (Schema::hasColumn('site_visits', 'kyc_custom_fields')) {
                $table->dropColumn('kyc_custom_fields');
            }

            if (Schema::hasColumn('site_visits', 'kyc_dynamic_form_id')) {
                $table->dropConstrainedForeignId('kyc_dynamic_form_id');
            }
        });
    }
};
