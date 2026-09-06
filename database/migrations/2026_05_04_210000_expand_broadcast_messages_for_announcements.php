<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->string('priority')->default('normal')->after('message');
            $table->boolean('banner_enabled')->default(false)->after('priority');
            $table->boolean('requires_acknowledge')->default(false)->after('banner_enabled');
            $table->string('action_label')->nullable()->after('requires_acknowledge');
            $table->text('action_url')->nullable()->after('action_label');
            $table->string('attachment_path')->nullable()->after('action_url');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->json('target_user_ids')->nullable()->after('target_roles');
            $table->timestamp('starts_at')->nullable()->after('target_user_ids');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->string('status')->default('active')->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropColumn([
                'priority',
                'banner_enabled',
                'requires_acknowledge',
                'action_label',
                'action_url',
                'attachment_path',
                'attachment_name',
                'target_user_ids',
                'starts_at',
                'ends_at',
                'status',
            ]);
        });
    }
};
