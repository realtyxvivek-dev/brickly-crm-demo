<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ig_conversations', function (Blueprint $table) {
            $table->string('comment_id')->nullable()->after('original_comment');
            $table->index(['instagram_account_id', 'comment_id'], 'ig_conversations_account_comment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ig_conversations', function (Blueprint $table) {
            $table->dropIndex('ig_conversations_account_comment_idx');
            $table->dropColumn('comment_id');
        });
    }
};
