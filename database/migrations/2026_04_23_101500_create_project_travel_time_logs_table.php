<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_travel_time_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('share_token')->nullable();
            $table->string('action', 40);
            $table->string('origin_source', 40)->nullable();
            $table->string('query')->nullable();
            $table->string('origin_label')->nullable();
            $table->string('status', 40)->nullable();
            $table->boolean('drive_available')->default(false);
            $table->boolean('walk_available')->default(false);
            $table->boolean('cache_hit')->default(false);
            $table->string('provider', 40)->default('ola');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_travel_time_logs');
    }
};
