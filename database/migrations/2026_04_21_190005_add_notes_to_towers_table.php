<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('towers', function (Blueprint $table) {
            if (!Schema::hasColumn('towers', 'notes')) {
                $table->string('notes')->nullable()->after('tower_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('towers', function (Blueprint $table) {
            if (Schema::hasColumn('towers', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
