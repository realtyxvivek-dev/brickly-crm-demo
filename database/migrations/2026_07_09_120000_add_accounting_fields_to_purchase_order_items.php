<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'expense_category_id')) {
                $table->foreignId('expense_category_id')->nullable()->after('category')->constrained('expense_categories')->nullOnDelete();
            }

            if (!Schema::hasColumn('purchase_order_items', 'expense_subcategory_id')) {
                $table->foreignId('expense_subcategory_id')->nullable()->after('expense_category_id')->constrained('expense_subcategories')->nullOnDelete();
            }

            if (!Schema::hasColumn('purchase_order_items', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('rate');
            }
        });

        DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNull('purchase_order_items.expense_category_id')
            ->update([
                'purchase_order_items.expense_category_id' => DB::raw('purchase_orders.expense_category_id'),
                'purchase_order_items.expense_subcategory_id' => DB::raw('purchase_orders.expense_subcategory_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'expense_subcategory_id')) {
                $table->dropConstrainedForeignId('expense_subcategory_id');
            }

            if (Schema::hasColumn('purchase_order_items', 'expense_category_id')) {
                $table->dropConstrainedForeignId('expense_category_id');
            }

            if (Schema::hasColumn('purchase_order_items', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
        });
    }
};
