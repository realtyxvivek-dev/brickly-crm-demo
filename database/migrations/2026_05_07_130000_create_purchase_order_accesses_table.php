<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_order_accesses')) {
            return;
        }

        Schema::create('purchase_order_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('can_raise_need_purchase')->default(false);
            $table->boolean('can_raise_reimbursement')->default(false);
            $table->boolean('can_mark_payment_done')->default(false);
            $table->decimal('payment_limit', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'can_raise_need_purchase']);
            $table->index(['is_active', 'can_raise_reimbursement']);
            $table->index(['is_active', 'can_mark_payment_done']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_accesses');
    }
};
