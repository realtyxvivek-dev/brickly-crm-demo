<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_oauth_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_oauth_connection_id')->constrained('meta_oauth_connections')->cascadeOnDelete();
            $table->string('page_id', 80)->index();
            $table->string('page_name')->nullable();
            $table->text('page_access_token')->nullable();
            $table->json('tasks')->nullable();
            $table->boolean('leadgen_subscribed')->default(false)->index();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['meta_oauth_connection_id', 'page_id'], 'meta_oauth_pages_connection_page_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_oauth_pages');
    }
};
