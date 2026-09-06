<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_oauth_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('meta_user_id', 80)->nullable()->index();
            $table->string('meta_user_name')->nullable();
            $table->text('user_access_token')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 40)->default('connected')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_oauth_connections');
    }
};
