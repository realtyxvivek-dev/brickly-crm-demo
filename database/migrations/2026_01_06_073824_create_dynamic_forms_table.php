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
        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Form name
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->text('description')->nullable();
            $table->string('location_path'); // Where form is used (e.g., 'leads/create', 'prospects/verify')
            $table->string('form_type')->default('custom'); // 'lead', 'prospect', 'meeting', 'custom', etc.
            $table->json('settings')->nullable(); // Form-level settings (submit button text, redirect URL, etc.)
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('slug');
            $table->index('form_type');
            $table->index('location_path');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_forms');
    }
};
