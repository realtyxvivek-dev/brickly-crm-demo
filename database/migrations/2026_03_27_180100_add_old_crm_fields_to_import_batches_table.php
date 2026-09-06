<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('import_kind')->nullable()->after('source_type');
            $table->foreignId('import_profile_id')
                ->nullable()
                ->after('automation_id')
                ->constrained('old_crm_import_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropForeign(['import_profile_id']);
            $table->dropColumn(['import_kind', 'import_profile_id']);
        });
    }
};
