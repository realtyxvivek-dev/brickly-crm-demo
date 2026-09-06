<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_mode', 20)->nullable()->after('is_active');
            $table->boolean('two_factor_enforced_by_admin')->default(false)->after('two_factor_mode');
            $table->boolean('otp_recovery_allowed')->default(false)->after('two_factor_enforced_by_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_mode',
                'two_factor_enforced_by_admin',
                'otp_recovery_allowed',
            ]);
        });
    }
};
