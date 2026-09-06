<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visit_revenue_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_id')->constrained('site_visits')->cascadeOnDelete();
            $table->decimal('old_revenue_value', 15, 2)->nullable();
            $table->decimal('new_revenue_value', 15, 2)->nullable();
            $table->text('old_revenue_note')->nullable();
            $table->text('new_revenue_note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['site_visit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visit_revenue_audits');
    }
};
