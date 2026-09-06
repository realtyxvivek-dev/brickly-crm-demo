<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('password_reset_otps')) {
            return;
        }

        Schema::table('password_reset_otps', function (Blueprint $table) {
            if (!Schema::hasColumn('password_reset_otps', 'otp_hash')) {
                $table->string('otp_hash', 255)->nullable()->after('otp');
            }
            if (!Schema::hasColumn('password_reset_otps', 'attempt_count')) {
                $table->unsignedTinyInteger('attempt_count')->default(0)->after('is_verified');
            }
            if (!Schema::hasColumn('password_reset_otps', 'resend_count')) {
                $table->unsignedTinyInteger('resend_count')->default(0)->after('attempt_count');
            }
            if (!Schema::hasColumn('password_reset_otps', 'last_sent_at')) {
                $table->timestamp('last_sent_at')->nullable()->after('resend_count');
            }
            if (!Schema::hasColumn('password_reset_otps', 'status')) {
                $table->string('status', 30)->default('pending')->after('last_sent_at');
            }
            if (!Schema::hasColumn('password_reset_otps', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('password_reset_otps', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('verified_at');
            }
            if (!Schema::hasColumn('password_reset_otps', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('used_at');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `password_reset_otps` MODIFY `otp` VARCHAR(6) NULL');
        }

        DB::table('password_reset_otps')->delete();
    }

    public function down(): void
    {
        if (!Schema::hasTable('password_reset_otps')) {
            return;
        }

        Schema::table('password_reset_otps', function (Blueprint $table) {
            foreach (['otp_hash', 'attempt_count', 'resend_count', 'last_sent_at', 'status', 'verified_at', 'used_at', 'locked_at'] as $column) {
                if (Schema::hasColumn('password_reset_otps', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `password_reset_otps` MODIFY `otp` VARCHAR(6) NOT NULL");
        }
    }
};
