<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fb_page_id')->constrained('fb_pages')->onDelete('cascade');
            $table->string('form_id', 50)->unique();
            $table->string('form_name')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->index('fb_page_id');
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_forms');
    }
};
