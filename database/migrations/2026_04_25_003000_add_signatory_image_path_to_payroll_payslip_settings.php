<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payroll_payslip_settings', 'signatory_image_path')) {
            Schema::table('payroll_payslip_settings', function (Blueprint $table) {
                $table->string('signatory_image_path')->nullable()->after('signatory_title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_payslip_settings', 'signatory_image_path')) {
            Schema::table('payroll_payslip_settings', function (Blueprint $table) {
                $table->dropColumn('signatory_image_path');
            });
        }
    }
};
