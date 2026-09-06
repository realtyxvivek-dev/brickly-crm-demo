<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'phone_country_iso')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('phone_country_iso', 2)->nullable()->after('normalized_phone')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'phone_country_iso')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex(['phone_country_iso']);
                $table->dropColumn('phone_country_iso');
            });
        }
    }
};
