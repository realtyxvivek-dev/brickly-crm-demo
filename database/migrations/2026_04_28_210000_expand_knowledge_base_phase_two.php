<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_base_items', function (Blueprint $table) {
            $table->string('visibility_type')->default('all_users')->after('status');
            $table->json('visible_role_slugs')->nullable()->after('visibility_type');
            $table->json('visible_user_ids')->nullable()->after('visible_role_slugs');
            $table->text('change_summary')->nullable()->after('thumbnail_path');
            $table->timestamp('content_updated_at')->nullable()->after('published_at');
        });

        Schema::table('knowledge_base_assignments', function (Blueprint $table) {
            $table->boolean('is_required')->default(true)->after('assigned_at');
            $table->date('due_date')->nullable()->after('is_required');
            $table->boolean('completed_late')->default(false)->after('due_date');
            $table->timestamp('last_reminded_at')->nullable()->after('completed_late');
        });

        Schema::table('knowledge_base_progress', function (Blueprint $table) {
            $table->unsignedTinyInteger('article_scroll_percent')->default(0)->after('article_seconds_viewed');
            $table->unsignedInteger('video_last_position_seconds')->default(0)->after('video_progress_percent');
            $table->unsignedInteger('pdf_seconds_viewed')->default(0)->after('pdf_interacted_at');
        });

        Schema::create('knowledge_base_paths', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_summary')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_path_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('knowledge_base_path_id');
            $table->unsignedBigInteger('knowledge_base_item_id');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['knowledge_base_path_id', 'knowledge_base_item_id'], 'kb_path_item_unique');
        });

        Schema::create('knowledge_base_path_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('knowledge_base_path_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_required')->default(true);
            $table->date('due_date')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_base_path_id', 'user_id'], 'kb_path_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_path_assignments');
        Schema::dropIfExists('knowledge_base_path_items');
        Schema::dropIfExists('knowledge_base_paths');

        Schema::table('knowledge_base_progress', function (Blueprint $table) {
            $table->dropColumn([
                'article_scroll_percent',
                'video_last_position_seconds',
                'pdf_seconds_viewed',
            ]);
        });

        Schema::table('knowledge_base_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'is_required',
                'due_date',
                'completed_late',
                'last_reminded_at',
            ]);
        });

        Schema::table('knowledge_base_items', function (Blueprint $table) {
            $table->dropColumn([
                'visibility_type',
                'visible_role_slugs',
                'visible_user_ids',
                'change_summary',
                'content_updated_at',
            ]);
        });
    }
};
