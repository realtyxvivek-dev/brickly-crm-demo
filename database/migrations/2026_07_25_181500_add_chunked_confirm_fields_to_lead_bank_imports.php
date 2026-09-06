<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_bank_import_sessions', function (Blueprint $table) {
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('folder_tag_id')
                ->constrained('import_batches')
                ->nullOnDelete();
            $table->unsignedInteger('failed_rows')->default(0)->after('skipped_rows');
        });

        Schema::table('lead_bank_import_rows', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('created_lead_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('lead_bank_import_rows', function (Blueprint $table) {
            $table->dropIndex(['processed_at']);
            $table->dropColumn('processed_at');
        });

        Schema::table('lead_bank_import_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropColumn('failed_rows');
        });
    }
};
