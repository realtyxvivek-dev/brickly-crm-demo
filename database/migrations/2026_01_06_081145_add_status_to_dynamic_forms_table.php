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
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])->default('draft')->after('is_active');
            $table->foreignId('replaces_form_id')->nullable()->after('status')->constrained('dynamic_forms')->onDelete('set null');
            
            $table->index('status');
            $table->index('replaces_form_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->dropForeign(['replaces_form_id']);
            $table->dropIndex(['replaces_form_id']);
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'replaces_form_id']);
        });
    }
};
