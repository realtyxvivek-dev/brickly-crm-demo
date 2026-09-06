<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_url_import_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('import_token')->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->text('normalized_url')->nullable();
            $table->string('parser_version')->nullable();
            $table->string('progress_stage')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('extracted_field_count')->default(0);
            $table->json('failed_selectors')->nullable();
            $table->json('warnings')->nullable();
            $table->string('status', 32)->default('failed')->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_url_import_logs');
    }
};
