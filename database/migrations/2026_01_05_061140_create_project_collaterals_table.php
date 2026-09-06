<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->enum('category', [
                'brochure',
                'floor_plans',
                'layout_plan',
                'price_sheet',
                'videos',
                'legal_approvals',
                'other'
            ]);
            $table->string('title');
            $table->text('link');
            $table->enum('link_type', ['youtube', 'google_drive'])->nullable();
            $table->boolean('is_latest')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'category']);
            $table->index('is_latest');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_collaterals');
    }
};
