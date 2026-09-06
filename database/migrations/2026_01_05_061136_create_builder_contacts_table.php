<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_id')->constrained('builders')->onDelete('cascade');
            $table->string('person_name');
            $table->string('mobile_number', 15);
            $table->string('whatsapp_number', 15)->nullable();
            $table->boolean('whatsapp_same_as_mobile')->default(false);
            $table->enum('preferred_mode', ['call', 'whatsapp', 'both'])->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['builder_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_contacts');
    }
};
