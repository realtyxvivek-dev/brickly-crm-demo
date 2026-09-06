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
        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_id')->constrained('site_visits')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['closer', 'site_visit'])->default('closer');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending_sales_head', 'pending_crm', 'verified', 'rejected'])->default('pending_sales_head');
            $table->foreignId('sales_head_verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('sales_head_verified_at')->nullable();
            $table->foreignId('crm_verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('crm_verified_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('site_visit_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incentives');
    }
};
