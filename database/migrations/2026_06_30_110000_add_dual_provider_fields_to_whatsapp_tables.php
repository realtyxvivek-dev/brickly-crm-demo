<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'last_inbound_at')) {
                $table->timestamp('last_inbound_at')->nullable()->after('lead_id');
            }
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'provider')) {
                $table->string('provider')->nullable()->after('template_id');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'external_message_id')) {
                $table->string('external_message_id')->nullable()->after('provider');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'provider_status')) {
                $table->string('provider_status')->nullable()->after('external_message_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            foreach (['provider_status', 'external_message_id', 'provider'] as $column) {
                if (Schema::hasColumn('whatsapp_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'last_inbound_at')) {
                $table->dropColumn('last_inbound_at');
            }
        });

    }
};
