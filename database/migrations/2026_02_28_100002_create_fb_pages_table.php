<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_id', 50)->unique();
            $table->string('page_name')->nullable();
            $table->string('token_reference')->nullable()->comment('Reference to token in settings or encrypted store');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_pages');
    }
};
