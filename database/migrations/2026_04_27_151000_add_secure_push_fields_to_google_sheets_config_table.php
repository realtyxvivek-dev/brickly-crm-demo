<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_sheets_config', function (Blueprint $table) {
            if (!Schema::hasColumn('google_sheets_config', 'inbound_slug')) {
                $table->string('inbound_slug', 64)->nullable()->after('api_key');
            }

            if (!Schema::hasColumn('google_sheets_config', 'inbound_api_key')) {
                $table->string('inbound_api_key', 80)->nullable()->after('inbound_slug');
            }
        });

        $existingIds = DB::table('google_sheets_config')->pluck('id');
        foreach ($existingIds as $id) {
            $row = DB::table('google_sheets_config')->where('id', $id)->first();
            $updates = [];

            if (!$row?->inbound_slug) {
                do {
                    $slug = 'gsi_' . Str::lower(Str::random(32));
                } while (DB::table('google_sheets_config')->where('inbound_slug', $slug)->exists());

                $updates['inbound_slug'] = $slug;
            }

            if (!$row?->inbound_api_key) {
                $updates['inbound_api_key'] = 'gsk_' . Str::lower(Str::random(40));
            }

            if ($updates !== []) {
                DB::table('google_sheets_config')->where('id', $id)->update($updates);
            }
        }

        Schema::table('google_sheets_config', function (Blueprint $table) {
            $table->unique('inbound_slug', 'google_sheets_config_inbound_slug_unique');
            $table->index('inbound_slug', 'google_sheets_config_inbound_slug_index');
        });
    }

    public function down(): void
    {
        Schema::table('google_sheets_config', function (Blueprint $table) {
            if (Schema::hasColumn('google_sheets_config', 'inbound_slug')) {
                $table->dropUnique('google_sheets_config_inbound_slug_unique');
                $table->dropIndex('google_sheets_config_inbound_slug_index');
                $table->dropColumn('inbound_slug');
            }

            if (Schema::hasColumn('google_sheets_config', 'inbound_api_key')) {
                $table->dropColumn('inbound_api_key');
            }
        });
    }
};
