<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_rule_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('assignment_rules')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('percentage', 5, 2)->default(0)->comment('Percentage value (0-100)');
            $table->timestamps();

            $table->unique(['rule_id', 'user_id']);
            $table->index('rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_rule_users');
    }
};

