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
        Schema::create('telecaller_daily_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->integer('overall_daily_limit')->default(0);
            $table->integer('assigned_count_today')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('last_reset_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telecaller_daily_limits');
    }
};
