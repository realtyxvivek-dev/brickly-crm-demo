<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sheet_import_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_sheets_config_id')
                ->unique()
                ->constrained('google_sheets_config')
                ->onDelete('cascade');
            $table->unsignedBigInteger('last_processed_row')->default(1);
            $table->dateTime('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('last_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheet_import_state');
    }
};

