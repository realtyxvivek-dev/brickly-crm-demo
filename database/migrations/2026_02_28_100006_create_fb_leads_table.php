<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_leads', function (Blueprint $table) {
            $table->id();
            $table->string('leadgen_id', 50)->unique();
            $table->foreignId('fb_form_id')->constrained('fb_forms')->onDelete('cascade');
            $table->json('field_data_json')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamps();

            $table->index('fb_form_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_leads');
    }
};
