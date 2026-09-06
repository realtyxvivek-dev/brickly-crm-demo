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
        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('dynamic_forms')->onDelete('cascade');
            $table->string('field_key'); // Database field name (e.g., 'name', 'phone')
            $table->string('field_type'); // 'text', 'email', 'number', 'select', 'textarea', 'checkbox', 'radio', 'date', 'file', etc.
            $table->string('label'); // Display label
            $table->text('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable(); // For select, radio, checkbox options
            $table->json('validation')->nullable(); // Validation rules (required, min, max, pattern, etc.)
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0); // Field order in form
            $table->string('section')->nullable(); // Group fields in sections (e.g., 'Basic Information', 'Location Details')
            $table->json('styles')->nullable(); // CSS classes, inline styles
            $table->string('default_value')->nullable(); // Default value for field
            $table->timestamps();
            
            $table->index('form_id');
            $table->index('order');
            $table->index('section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_form_fields');
    }
};
