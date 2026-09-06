<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_files', function (Blueprint $table) {
            $table->id();
            $table->enum('file_type', ['logo', 'favicon', 'email_header', 'email_footer']);
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->integer('file_size'); // Size in bytes
            $table->string('mime_type', 100);
            $table->string('dimensions', 50)->nullable(); // Width x Height (for images)
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            // Indexes
            $table->unique(['file_type', 'is_active']); // Only one active file per type
            $table->index('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_files');
    }
};
