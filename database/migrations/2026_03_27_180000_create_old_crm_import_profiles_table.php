<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('old_crm_import_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('header_signature', 64);
            $table->json('headers')->nullable();
            $table->json('mapping_config');
            $table->json('stage_mapping')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'header_signature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('old_crm_import_profiles');
    }
};
