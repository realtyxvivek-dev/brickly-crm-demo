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
        Schema::create('sla_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_assignment_id')->constrained('smart_import_lead_assignments')->onDelete('cascade');
            $table->integer('sla_minutes');
            $table->dateTime('started_at');
            $table->dateTime('first_contact_at')->nullable();
            $table->dateTime('breached_at')->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'met', 'breached', 'escalated'])->default('pending');
            $table->timestamps();

            $table->index(['lead_assignment_id', 'status']);
            $table->index('started_at');
            $table->index('breached_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_tracking');
    }
};
