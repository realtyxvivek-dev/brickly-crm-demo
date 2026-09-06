<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->json('raw_payload')->nullable();
            $table->string('leadgen_id', 50)->nullable()->index();
            $table->string('status', 50)->default('received')->comment('received, processed, failed');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_webhook_events');
    }
};
