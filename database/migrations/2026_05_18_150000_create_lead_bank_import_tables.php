<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_bank_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_file_name');
            $table->string('stored_path');
            $table->string('source_type')->default('csv');
            $table->json('headers')->nullable();
            $table->json('column_mapping')->nullable();
            $table->json('default_tags')->nullable();
            $table->enum('status', ['draft', 'ready', 'imported', 'cancelled'])->default('draft');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('included_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'lb_import_sessions_user_status_idx');
        });

        Schema::create('lead_bank_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_bank_import_session_id')->constrained('lead_bank_import_sessions')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data')->nullable();
            $table->json('mapped_data')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->json('tags')->nullable();
            $table->enum('validation_status', ['valid', 'duplicate', 'invalid', 'blocked', 'malformed'])->default('valid');
            $table->json('errors')->nullable();
            $table->boolean('include')->default(true);
            $table->enum('import_action', ['create', 'update_existing', 'skip'])->default('create');
            $table->foreignId('existing_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('created_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_bank_import_session_id', 'validation_status'], 'lb_import_rows_session_validation_idx');
            $table->index('normalized_phone', 'lb_import_rows_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_bank_import_rows');
        Schema::dropIfExists('lead_bank_import_sessions');
    }
};
