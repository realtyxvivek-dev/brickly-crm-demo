<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'status')) {
                $table->string('status', 32)->default('open')->after('assigned_to')->index();
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('last_inbound_at');
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('whatsapp_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::table('waba_campaign_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('waba_campaign_recipients', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('error_message');
            }
            if (!Schema::hasColumn('waba_campaign_recipients', 'last_retry_at')) {
                $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('waba_campaign_recipients', function (Blueprint $table) {
            foreach (['last_retry_at', 'retry_count'] as $column) {
                if (Schema::hasColumn('waba_campaign_recipients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('whatsapp_conversation_notes');
        Schema::dropIfExists('whatsapp_quick_replies');

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'resolved_by')) {
                $table->dropConstrainedForeignId('resolved_by');
            }
            if (Schema::hasColumn('whatsapp_conversations', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
            if (Schema::hasColumn('whatsapp_conversations', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
                $table->dropConstrainedForeignId('assigned_to');
            }
        });
    }
};
