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
        Schema::create('blacklisted_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique()->index();
            $table->string('reason')->default('Broker');
            $table->foreignId('blacklisted_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('blacklisted_at');
            $table->timestamps();

            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklisted_numbers');
    }
};
