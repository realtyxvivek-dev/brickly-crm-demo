<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution_task_attachments', function (Blueprint $table) {
            $table->string('attachment_kind', 20)->default('file')->after('task_id');
            $table->text('link_url')->nullable()->after('mime_type');
            $table->string('link_title')->nullable()->after('link_url');

            $table->index('attachment_kind');
        });
    }

    public function down(): void
    {
        Schema::table('execution_task_attachments', function (Blueprint $table) {
            $table->dropIndex(['attachment_kind']);
            $table->dropColumn(['attachment_kind', 'link_url', 'link_title']);
        });
    }
};
