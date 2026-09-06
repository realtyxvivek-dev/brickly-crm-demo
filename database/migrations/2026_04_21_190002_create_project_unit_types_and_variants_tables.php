<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_unit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('project_size_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_unit_type_id')->constrained('project_unit_types')->cascadeOnDelete();
            $table->string('size_label');
            $table->decimal('carpet_area_sqft', 12, 2)->nullable();
            $table->decimal('builtup_area_sqft', 12, 2)->nullable();
            $table->decimal('base_rate_per_sqft', 15, 2)->nullable();
            $table->enum('rounding_rule', ['none', 'nearest_1000', 'nearest_10000', 'nearest_100000'])->nullable();
            $table->decimal('calculated_price', 15, 2)->nullable();
            $table->decimal('manual_price_override', 15, 2)->nullable();
            $table->decimal('final_price', 15, 2)->nullable();
            $table->boolean('is_price_on_request')->default(false);
            $table->enum('status', ['available', 'hold', 'sold_out', 'hidden'])->default('available');
            $table->boolean('visible_on_public_page')->default(true);
            $table->string('floor_plan_image_path')->nullable();
            $table->string('details_pdf_path')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'project_unit_type_id']);
        });

        Schema::create('project_tower_variant_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('tower_id')->constrained('towers')->cascadeOnDelete();
            $table->foreignId('project_size_variant_id')->constrained('project_size_variants')->cascadeOnDelete();
            $table->string('inventory_notes')->nullable();
            $table->string('facing')->nullable();
            $table->string('floor_range')->nullable();
            $table->timestamps();

            $table->unique(['tower_id', 'project_size_variant_id'], 'tower_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tower_variant_map');
        Schema::dropIfExists('project_size_variants');
        Schema::dropIfExists('project_unit_types');
    }
};
