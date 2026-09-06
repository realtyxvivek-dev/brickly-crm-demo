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
        Schema::create('source_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('source', ['facebook_lead_ads', 'pabbly', 'mcube', 'google_sheets', 'csv', 'all']);
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->enum('assignment_method', ['round_robin', 'first_available', 'percentage', 'single_user']);
            $table->unsignedBigInteger('single_user_id')->nullable();
            $table->boolean('auto_create_task')->default(true);
            $table->integer('daily_limit')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('fb_form_id')->references('id')->on('fb_forms')->nullOnDelete();
            $table->foreign('single_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('fallback_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_automation_rules');
    }
};
