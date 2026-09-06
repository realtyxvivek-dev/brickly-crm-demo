<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('android');
            $table->unsignedInteger('installed_version_code')->default(0);
            $table->string('installed_version_name', 50)->nullable();
            $table->unsignedInteger('last_download_version_code')->nullable();
            $table->string('last_download_version_name', 50)->nullable();
            $table->unsignedInteger('download_click_count')->default(0);
            $table->text('fcm_token')->nullable();
            $table->string('app_build_label', 100)->nullable();
            $table->string('device_label', 150)->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamp('last_download_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform']);
            $table->index(['platform', 'installed_version_code']);
            $table->index('last_opened_at');
            $table->index('last_download_clicked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_installations');
    }
};
