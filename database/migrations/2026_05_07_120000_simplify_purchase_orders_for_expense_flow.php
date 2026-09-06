<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_orders', 'request_type')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('request_type')->default('need_purchase')->after('purchase_type');
            });
        }

        if (!Schema::hasColumn('purchase_orders', 'expense_category_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('expense_category_id')->nullable()->after('vendor_name')->constrained('expense_categories')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('purchase_orders', 'expense_subcategory_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('expense_subcategory_id')->nullable()->after('expense_category_id')->constrained('expense_subcategories')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('purchase_orders', 'expense_entry_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('expense_entry_id')->nullable()->after('received_attachment_path')->constrained('expense_entries')->nullOnDelete();
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!$this->indexExists('purchase_orders_request_type_status_index')) {
                $table->index(['request_type', 'status']);
            }

            if (!$this->indexExists('purchase_orders_expense_category_id_expense_subcategory_id_index')) {
                $table->index(['expense_category_id', 'expense_subcategory_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if ($this->indexExists('purchase_orders_request_type_status_index')) {
                $table->dropIndex(['request_type', 'status']);
            }

            if ($this->indexExists('purchase_orders_expense_category_id_expense_subcategory_id_index')) {
                $table->dropIndex(['expense_category_id', 'expense_subcategory_id']);
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'expense_entry_id')) {
                $table->dropConstrainedForeignId('expense_entry_id');
            }

            if (Schema::hasColumn('purchase_orders', 'expense_subcategory_id')) {
                $table->dropConstrainedForeignId('expense_subcategory_id');
            }

            if (Schema::hasColumn('purchase_orders', 'expense_category_id')) {
                $table->dropConstrainedForeignId('expense_category_id');
            }

            if (Schema::hasColumn('purchase_orders', 'request_type')) {
                $table->dropColumn('request_type');
            }
        });
    }

    private function indexExists(string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'purchase_orders')
            ->where('index_name', $indexName)
            ->exists();
    }
};
