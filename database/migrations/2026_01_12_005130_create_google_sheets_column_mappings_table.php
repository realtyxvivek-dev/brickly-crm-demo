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
        Schema::create('google_sheets_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_sheets_config_id')->constrained('google_sheets_config')->onDelete('cascade');
            $table->string('sheet_column', 10)->comment('Column letter (A-Z)');
            $table->string('lead_field_key', 100)->comment('Field key in leads table or custom');
            $table->enum('field_type', ['standard', 'custom', 'form_field'])->default('custom');
            $table->string('field_label', 255)->comment('Display label for the field');
            $table->boolean('is_required')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['google_sheets_config_id', 'sheet_column'], 'gs_cm_config_column_unique');
            $table->index('google_sheets_config_id');
            $table->index('sheet_column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_sheets_column_mappings');
    }
};
