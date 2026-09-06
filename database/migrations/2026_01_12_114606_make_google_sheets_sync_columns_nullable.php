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
        Schema::table('google_sheets_config', function (Blueprint $table) {
            $table->string('notes_column', 10)->nullable()->change();
            $table->string('status_column', 10)->nullable()->change();
            $table->string('notes_column_sync', 10)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_sheets_config', function (Blueprint $table) {
            $table->string('notes_column', 10)->default('C')->change();
            $table->string('status_column', 10)->default('D')->change();
            $table->string('notes_column_sync', 10)->default('E')->change();
        });
    }
};
