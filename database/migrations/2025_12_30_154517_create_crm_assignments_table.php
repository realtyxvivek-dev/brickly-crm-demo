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
        Schema::create('crm_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->string('customer_name');
            $table->string('phone')->index();
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('assigned_at');
            $table->enum('call_status', ['pending', 'called_interested', 'called_not_interested', 'completed'])->default('pending');
            $table->timestamp('called_at')->nullable();
            $table->integer('cnp_count')->default(0);
            $table->dateTime('follow_up_scheduled_at')->nullable();
            $table->boolean('follow_up_completed')->default(false);
            $table->date('not_interested_date')->nullable();
            $table->date('shuffle_after_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('sheet_config_id')->nullable()->constrained('google_sheets_config')->onDelete('set null');
            $table->integer('sheet_row_number')->nullable();
            $table->timestamps();

            $table->index('assigned_to');
            $table->index('call_status');
            $table->index('follow_up_scheduled_at');
            $table->index('assigned_at');
            $table->index(['assigned_to', 'call_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_assignments');
    }
};
