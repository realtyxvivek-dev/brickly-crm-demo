<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_automation_rule_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('source_automation_rules')->cascadeOnDelete();
            $table->foreignId('fb_form_id')->constrained('fb_forms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rule_id', 'fb_form_id'], 'source_auto_rule_forms_rule_form_unique');
            $table->index('fb_form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_automation_rule_forms');
    }
};
