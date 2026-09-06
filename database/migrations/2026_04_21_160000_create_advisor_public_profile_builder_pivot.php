<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('advisor_public_profile_builder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_public_profile_id')
                ->constrained('advisor_public_profiles')
                ->cascadeOnDelete();
            $table->foreignId('builder_id')
                ->constrained('builders')
                ->cascadeOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['advisor_public_profile_id', 'builder_id'],
                'advisor_builder_unique'
            );
            $table->index(['advisor_public_profile_id', 'is_featured'], 'advisor_builder_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_public_profile_builder');
    }
};
