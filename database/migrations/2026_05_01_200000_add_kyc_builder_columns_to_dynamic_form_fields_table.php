<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dynamic_form_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('dynamic_form_fields', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('default_value');
            }

            if (!Schema::hasColumn('dynamic_form_fields', 'system_binding')) {
                $table->string('system_binding')->nullable()->after('is_system');
            }

            if (!Schema::hasColumn('dynamic_form_fields', 'is_visible')) {
                $table->boolean('is_visible')->default(true)->after('system_binding');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_form_fields', function (Blueprint $table) {
            if (Schema::hasColumn('dynamic_form_fields', 'is_visible')) {
                $table->dropColumn('is_visible');
            }

            if (Schema::hasColumn('dynamic_form_fields', 'system_binding')) {
                $table->dropColumn('system_binding');
            }

            if (Schema::hasColumn('dynamic_form_fields', 'is_system')) {
                $table->dropColumn('is_system');
            }
        });
    }
};
