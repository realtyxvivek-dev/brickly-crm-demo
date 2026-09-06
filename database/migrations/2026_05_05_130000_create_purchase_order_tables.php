<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->nullable()->unique();
            $table->string('po_number')->nullable()->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->string('purchase_type');
            $table->string('vendor_name');
            $table->text('purpose');
            $table->date('required_by_date')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('delivery_location')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('service_period')->nullable();
            $table->text('license_note')->nullable();
            $table->date('renewal_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('admin_reviewed_by')->nullable()->constrained('users');
            $table->timestamp('admin_reviewed_at')->nullable();
            $table->text('admin_remark')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->date('received_date')->nullable();
            $table->text('receiving_note')->nullable();
            $table->string('received_attachment_path')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->string('category')->default('other');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_order_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('action_by')->nullable()->constrained('users');
            $table->text('remark')->nullable();
            $table->timestamp('action_at');
            $table->timestamps();

            $table->index(['purchase_order_id', 'action_at']);
        });

        Schema::create('purchase_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode');
            $table->foreignId('expense_payment_method_id')->nullable()->constrained('expense_payment_methods')->nullOnDelete();
            $table->string('reference_no')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('paid_by')->constrained('users');
            $table->date('paid_at');
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_payments');
        Schema::dropIfExists('purchase_order_approval_logs');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
