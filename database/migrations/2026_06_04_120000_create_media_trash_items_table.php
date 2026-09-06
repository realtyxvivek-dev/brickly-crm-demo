<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_trash_items', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('source_field')->nullable();
            $table->string('module');
            $table->string('media_type');
            $table->string('disk')->default('public');
            $table->text('file_path');
            $table->text('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_protected')->default(false);
            $table->string('protected_reason')->nullable();
            $table->foreignId('trashed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('delete_after')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('permanently_deleted_at')->nullable();
            $table->foreignId('permanently_deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['module', 'media_type']);
            $table->index(['trashed_at', 'restored_at', 'permanently_deleted_at'], 'media_trash_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_trash_items');
    }
};
