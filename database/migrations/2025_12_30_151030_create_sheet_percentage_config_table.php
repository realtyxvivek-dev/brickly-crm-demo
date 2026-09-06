<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sheet_percentage_config')) {
            Schema::create('sheet_percentage_config', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sheet_assignment_config_id')->constrained('sheet_assignment_config')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->decimal('percentage', 5, 2);
                $table->integer('daily_limit')->nullable();
                $table->integer('assigned_count_today')->default(0);
                $table->date('last_reset_date')->nullable();
                $table->timestamps();

                $table->unique(['sheet_assignment_config_id', 'user_id'], 'spc_sac_id_user_id_unique');
                $table->index('sheet_assignment_config_id');
                $table->index('user_id');
                $table->index('last_reset_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sheet_percentage_config');
    }
};
