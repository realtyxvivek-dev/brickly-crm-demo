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
        Schema::create('source_automation_rule_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('percentage', 5, 2)->nullable();
            $table->integer('daily_limit')->nullable();
            $table->integer('assigned_count_today')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->timestamps();

            $table->foreign('rule_id')->references('id')->on('source_automation_rules')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_automation_rule_users');
    }
};
