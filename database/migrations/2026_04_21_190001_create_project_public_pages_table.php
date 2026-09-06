<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_public_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->text('short_intro')->nullable();
            $table->json('featured_badges')->nullable();
            $table->string('hero_cover_path')->nullable();
            $table->string('builder_logo_path')->nullable();
            $table->text('map_embed')->nullable();
            $table->text('location_summary')->nullable();
            $table->decimal('base_rate_per_sqft', 15, 2)->nullable();
            $table->enum('rounding_rule', ['none', 'nearest_1000', 'nearest_10000', 'nearest_100000'])->default('none');
            $table->boolean('towers_enabled')->default(false);
            $table->boolean('show_call')->default(true);
            $table->boolean('show_whatsapp')->default(true);
            $table->boolean('show_book_visit')->default(true);
            $table->boolean('show_request_callback')->default(false);
            $table->boolean('show_downloads')->default(true);
            $table->boolean('show_video')->default(true);
            $table->boolean('show_tour_360')->default(true);
            $table->string('call_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->text('book_visit_url')->nullable();
            $table->text('callback_url')->nullable();
            $table->enum('status', ['draft', 'hidden', 'published'])->default('draft');
            $table->string('preview_token')->nullable()->unique();
            $table->timestamp('last_saved_at')->nullable();
            $table->foreignId('last_saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_public_pages');
    }
};
