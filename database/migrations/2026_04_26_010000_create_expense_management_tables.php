<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->foreignId('expense_subcategory_id')->constrained('expense_subcategories');
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_mode', 20);
            $table->string('paid_to')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('approved');
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['company_id', 'expense_date']);
            $table->index(['expense_category_id', 'expense_subcategory_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_entries');
        Schema::dropIfExists('expense_subcategories');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('companies');
    }
};
