<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payroll_payslips', 'verification_token')) {
            Schema::table('payroll_payslips', function (Blueprint $table) {
                $table->string('verification_token', 80)->nullable()->unique()->after('payslip_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_payslips', 'verification_token')) {
            Schema::table('payroll_payslips', function (Blueprint $table) {
                $table->dropUnique(['verification_token']);
                $table->dropColumn('verification_token');
            });
        }
    }
};
