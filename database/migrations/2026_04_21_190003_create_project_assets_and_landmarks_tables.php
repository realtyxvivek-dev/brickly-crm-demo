<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->enum('asset_type', [
                'gallery_image',
                'price_sheet',
                'brochure',
                'video',
                'tour_360',
            ]);
            $table->string('title')->nullable();
            $table->string('mime_type')->nullable();
            $table->enum('source_type', ['uploaded', 'generated', 'external'])->default('uploaded');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('preview_image_path')->nullable();
            $table->string('tracking_key');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('project_landmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('label');
            $table->string('type')->nullable();
            $table->string('distance_text')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_landmarks');
        Schema::dropIfExists('project_assets');
    }
};
