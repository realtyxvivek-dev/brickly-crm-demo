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
        Schema::create('user_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->boolean('is_online')->default(false);
            $table->dateTime('last_seen_at')->nullable();
            $table->time('office_hours_start')->nullable();
            $table->time('office_hours_end')->nullable();
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->integer('max_leads_per_day')->nullable();
            $table->integer('current_day_leads')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->boolean('is_available')->default(false);
            $table->timestamps();

            $table->index(['is_online', 'is_available']);
            $table->index('last_reset_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_availability');
    }
};
