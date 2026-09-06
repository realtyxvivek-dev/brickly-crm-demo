<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_templates', 'provider')) {
                $table->string('provider')->default('third_party')->after('id');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'status')) {
                $table->string('status')->default('APPROVED')->after('language');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'components')) {
                $table->json('components')->nullable()->after('content');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('components');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            foreach (['rejection_reason', 'raw_payload', 'components', 'status', 'provider'] as $column) {
                if (Schema::hasColumn('whatsapp_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
