<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_auditor_user_access', function (Blueprint $table) {
            $table->foreignId('auditor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sales_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['auditor_id', 'sales_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_auditor_user_access');
    }
};
