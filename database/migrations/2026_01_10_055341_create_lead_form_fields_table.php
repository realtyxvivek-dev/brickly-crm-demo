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
        Schema::create('lead_form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_key')->unique()->comment('Unique identifier for the field, e.g., category, preferred_location');
            $table->string('field_label')->comment('Display name for the field');
            $table->enum('field_type', ['text', 'textarea', 'select', 'date', 'time', 'number', 'email', 'tel'])->comment('Type of input field');
            $table->enum('field_level', ['telecaller', 'sales_executive', 'sales_manager'])->comment('Which level can see/edit this field');
            $table->json('options')->nullable()->comment('For select/dropdown fields, stores options array');
            $table->boolean('is_required')->default(false)->comment('Whether field is required');
            $table->json('validation_rules')->nullable()->comment('Additional validation rules');
            $table->string('dependent_field')->nullable()->comment('Field this depends on, e.g., type depends on category');
            $table->json('dependent_conditions')->nullable()->comment('Conditions for dependent fields');
            $table->integer('display_order')->default(0)->comment('Order in which field appears');
            $table->boolean('is_active')->default(true)->comment('Whether field is active/enabled');
            $table->string('default_value')->nullable()->comment('Default value for the field');
            $table->string('placeholder')->nullable()->comment('Placeholder text');
            $table->text('help_text')->nullable()->comment('Help text displayed below field');
            $table->timestamps();
            
            $table->index('field_level');
            $table->index('is_active');
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_form_fields');
    }
};
