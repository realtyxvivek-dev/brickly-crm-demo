<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('unit_type');
            $table->decimal('area_sqft', 10, 2);
            $table->decimal('calculated_price', 15, 2)->nullable();
            $table->string('display_label')->nullable();
            $table->boolean('is_starting_from')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('is_starting_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
