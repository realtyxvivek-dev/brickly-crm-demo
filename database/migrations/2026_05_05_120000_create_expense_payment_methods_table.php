<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('name');
            $table->string('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('sort_order');
        });

        Schema::table('expense_entries', function (Blueprint $table) {
            $table->foreignId('expense_payment_method_id')
                ->nullable()
                ->after('payment_mode')
                ->constrained('expense_payment_methods')
                ->nullOnDelete();
        });

        DB::table('expense_payment_methods')->insert([
            [
                'type' => 'cash',
                'name' => 'Cash',
                'details' => null,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'bank',
                'name' => 'Bank Account',
                'details' => 'Default bank payment source',
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'upi',
                'name' => 'UPI Account',
                'details' => 'Default UPI payment source',
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'credit_card',
                'name' => 'Credit Card',
                'details' => 'Default credit card payment source',
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('expense_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_payment_method_id');
        });

        Schema::dropIfExists('expense_payment_methods');
    }
};
