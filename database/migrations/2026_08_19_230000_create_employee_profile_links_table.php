<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_profiles') && !Schema::hasColumn('employee_profiles', 'date_of_birth')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->date('date_of_birth')->nullable()->after('joining_date');
            });
        }

        if (!Schema::hasTable('employee_profile_links')) {
            Schema::create('employee_profile_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
                $table->string('token', 96)->unique();
                $table->string('status', 30)->default('active');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('last_opened_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_profile_id', 'status']);
                $table->index(['status', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profile_links');

        if (Schema::hasTable('employee_profiles') && Schema::hasColumn('employee_profiles', 'date_of_birth')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->dropColumn('date_of_birth');
            });
        }
    }
};
