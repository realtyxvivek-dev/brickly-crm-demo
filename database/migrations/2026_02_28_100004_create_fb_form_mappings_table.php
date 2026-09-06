<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_form_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fb_form_id')->constrained('fb_forms')->onDelete('cascade');
            $table->json('mapping_json')->comment('FB field name => CRM field key');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('fb_form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_form_mappings');
    }
};
