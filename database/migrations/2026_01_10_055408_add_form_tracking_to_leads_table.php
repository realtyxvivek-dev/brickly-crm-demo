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
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('form_filled_by_telecaller')->default(false)->after('status_auto_update_enabled');
            $table->boolean('form_filled_by_executive')->default(false)->after('form_filled_by_telecaller');
            $table->boolean('form_filled_by_manager')->default(false)->after('form_filled_by_executive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['form_filled_by_telecaller', 'form_filled_by_executive', 'form_filled_by_manager']);
        });
    }
};
