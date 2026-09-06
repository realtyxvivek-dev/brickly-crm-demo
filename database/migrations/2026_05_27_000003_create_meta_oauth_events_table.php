<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_oauth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_oauth_page_id')->nullable()->constrained('meta_oauth_pages')->nullOnDelete();
            $table->string('page_id', 80)->nullable()->index();
            $table->string('form_id', 80)->nullable()->index();
            $table->string('leadgen_id', 80)->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->string('status', 40)->default('received')->index();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_oauth_events');
    }
};
