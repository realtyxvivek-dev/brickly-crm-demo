<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_message_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['broadcast_message_id', 'user_id'], 'broadcast_message_user_state_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_message_user_states');
    }
};
