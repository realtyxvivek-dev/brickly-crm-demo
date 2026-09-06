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
        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->string('field_key')->comment('Matches lead_form_fields.field_key');
            $table->text('field_value')->nullable()->comment('Actual value stored');
            $table->foreignId('filled_by_user_id')->nullable()->constrained('users')->onDelete('set null')->comment('Who filled this field');
            $table->timestamp('filled_at')->nullable()->comment('When field was filled');
            $table->timestamps();
            
            $table->index('lead_id');
            $table->index('field_key');
            $table->unique(['lead_id', 'field_key'], 'lead_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_form_field_values');
    }
};
