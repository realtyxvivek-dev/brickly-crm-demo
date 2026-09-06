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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->nullable()->constrained('crm_assignments')->onDelete('set null');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->string('customer_name');
            $table->string('phone')->index();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('preferred_location')->nullable();
            $table->string('size')->nullable();
            $table->string('purpose')->nullable();
            $table->string('possession')->nullable();
            $table->foreignId('assigned_manager')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->text('employee_remark')->nullable();
            $table->enum('verification_status', ['pending_verification', 'verified', 'rejected'])->default('pending_verification');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('created_by');
            $table->index('verification_status');
            $table->index('assigned_manager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
