<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('android');
            $table->string('app_version_name', 50)->nullable();
            $table->unsignedInteger('app_version_code')->nullable();
            $table->string('app_build_label', 100)->nullable();
            $table->string('device_model', 150)->nullable();
            $table->string('manufacturer', 80)->nullable();
            $table->string('android_version', 50)->nullable();
            $table->unsignedInteger('sdk_int')->nullable();
            $table->string('health_status', 20)->default('unknown');
            $table->json('permissions')->nullable();
            $table->json('features')->nullable();
            $table->json('test_results')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'reported_at']);
            $table->index(['health_status', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_diagnostics');
    }
};
