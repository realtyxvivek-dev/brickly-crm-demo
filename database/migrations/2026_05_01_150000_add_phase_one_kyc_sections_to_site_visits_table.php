<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'primary_applicant_details')) {
                $table->json('primary_applicant_details')->nullable()->after('kyc_documents');
            }

            if (!Schema::hasColumn('site_visits', 'joint_applicant_details')) {
                $table->json('joint_applicant_details')->nullable()->after('primary_applicant_details');
            }

            if (!Schema::hasColumn('site_visits', 'unit_details')) {
                $table->json('unit_details')->nullable()->after('joint_applicant_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('site_visits', 'primary_applicant_details') ? 'primary_applicant_details' : null,
                Schema::hasColumn('site_visits', 'joint_applicant_details') ? 'joint_applicant_details' : null,
                Schema::hasColumn('site_visits', 'unit_details') ? 'unit_details' : null,
            ]);

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
