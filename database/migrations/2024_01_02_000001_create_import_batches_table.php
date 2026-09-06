<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->enum('source_type', ['csv', 'google_sheets'])->default('csv');
            $table->string('file_name')->nullable();
            $table->string('google_sheet_id')->nullable();
            $table->string('google_sheet_name')->nullable();
            $table->integer('total_leads')->default(0);
            $table->integer('imported_leads')->default(0);
            $table->integer('failed_leads')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedBigInteger('assignment_rule_id')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};

