<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('advisor_public_profile_builder', function (Blueprint $table) {
            if (!Schema::hasColumn('advisor_public_profile_builder', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('builder_id');
                $table->index(['advisor_public_profile_id', 'is_hidden'], 'advisor_builder_hidden_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('advisor_public_profile_builder', function (Blueprint $table) {
            if (Schema::hasColumn('advisor_public_profile_builder', 'is_hidden')) {
                $table->dropIndex('advisor_builder_hidden_idx');
                $table->dropColumn('is_hidden');
            }
        });
    }
};
