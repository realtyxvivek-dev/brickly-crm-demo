<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_sheet_cell_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_key', 40)->default('master')->index();
            $table->string('row_key', 120)->index();
            $table->string('column_key', 80)->index();
            $table->text('value')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sheet_key', 'row_key', 'column_key'], 'insight_sheet_override_unique');
        });

        Schema::create('insight_sheet_cell_audits', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_key', 40)->default('master')->index();
            $table->string('row_key', 120)->index();
            $table->string('column_key', 80)->index();
            $table->text('source_value')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_sheet_cell_audits');
        Schema::dropIfExists('insight_sheet_cell_overrides');
    }
};
