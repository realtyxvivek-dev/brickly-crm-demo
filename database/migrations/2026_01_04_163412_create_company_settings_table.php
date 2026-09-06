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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->enum('setting_type', ['text', 'number', 'boolean', 'json', 'file'])->default('text');
            $table->string('category', 50); // 'company_profile' or 'branding'
            $table->string('group', 50)->nullable(); // Sub-group (e.g., 'contact', 'tax', 'colors')
            $table->string('display_label', 255);
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->text('validation_rules')->nullable(); // JSON validation rules
            $table->text('help_text')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index('category');
            $table->index(['category', 'group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
