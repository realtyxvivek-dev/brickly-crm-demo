<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_oauth_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_oauth_page_id')->constrained('meta_oauth_pages')->cascadeOnDelete();
            $table->string('form_id', 80)->index();
            $table->string('form_name')->nullable();
            $table->string('status', 80)->nullable();
            $table->timestamp('meta_created_time')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['meta_oauth_page_id', 'form_id'], 'meta_oauth_forms_page_form_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_oauth_forms');
    }
};
