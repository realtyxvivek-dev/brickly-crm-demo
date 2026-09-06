<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sheet_assignment_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_sheets_config_id')->unique()->constrained('google_sheets_config')->onDelete('cascade');
            $table->enum('assignment_method', ['manual', 'round_robin', 'first_available', 'percentage'])->default('manual');
            $table->boolean('auto_assign_enabled')->default(false);
            $table->foreignId('linked_telecaller_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('per_sheet_daily_limit')->nullable();
            $table->json('percentage_config')->nullable();
            $table->timestamps();

            $table->index('google_sheets_config_id');
            $table->index('linked_telecaller_id');
            $table->index('auto_assign_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sheet_assignment_config');
    }
};
