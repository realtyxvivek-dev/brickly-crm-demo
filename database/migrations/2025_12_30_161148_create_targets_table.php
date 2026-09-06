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
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('target_month'); // Format: YYYY-MM-01 (first day of month)
            $table->integer('target_visits')->default(0);
            $table->integer('target_meetings')->default(0);
            $table->integer('target_closers')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('target_month');
            $table->unique(['user_id', 'target_month']); // One target per user per month
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
