<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('smart_import_automations')->onDelete('cascade');
            $table->string('action', 50);
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['automation_id', 'action']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_audit_logs');
    }
};
