<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_tags', function (Blueprint $table) {
            $table->boolean('is_folder')->default(false)->after('color')->index();
        });

        Schema::table('lead_bank_import_sessions', function (Blueprint $table) {
            $table->string('folder_name', 80)->nullable()->after('default_tags');
            $table->string('folder_color', 20)->nullable()->after('folder_name');
            $table->foreignId('folder_tag_id')
                ->nullable()
                ->after('folder_color')
                ->constrained('lead_tags')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_bank_import_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_tag_id');
            $table->dropColumn(['folder_name', 'folder_color']);
        });

        Schema::table('lead_tags', function (Blueprint $table) {
            $table->dropIndex(['is_folder']);
            $table->dropColumn('is_folder');
        });
    }
};
