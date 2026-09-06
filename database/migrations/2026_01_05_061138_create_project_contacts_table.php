<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('builder_contact_id')->constrained('builder_contacts')->onDelete('cascade');
            $table->enum('contact_role', ['primary', 'secondary', 'escalation']);
            $table->timestamps();

            // Ensure one primary per project
            $table->unique(['project_id', 'contact_role']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contacts');
    }
};
