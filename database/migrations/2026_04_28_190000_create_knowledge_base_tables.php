<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_base_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('knowledge_base_categories')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_summary')->nullable();
            $table->longText('article_content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_item_id')->constrained('knowledge_base_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_base_item_id', 'user_id'], 'kb_assignments_item_user_unique');
        });

        Schema::create('knowledge_base_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_item_id')->constrained('knowledge_base_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->unsignedInteger('article_seconds_viewed')->default(0);
            $table->unsignedTinyInteger('video_progress_percent')->default(0);
            $table->timestamp('pdf_opened_at')->nullable();
            $table->timestamp('pdf_interacted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_base_item_id', 'user_id'], 'kb_progress_item_user_unique');
        });

        DB::table('knowledge_base_categories')->insert([
            ['name' => 'CRM Usage', 'slug' => 'crm-usage', 'description' => null, 'display_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Lead Management', 'slug' => 'lead-management', 'description' => null, 'display_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Follow-up SOP', 'slug' => 'follow-up-sop', 'description' => null, 'display_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Project Training', 'slug' => 'project-training', 'description' => null, 'display_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Reports', 'slug' => 'reports', 'description' => null, 'display_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'WhatsApp / Calling', 'slug' => 'whatsapp-calling', 'description' => null, 'display_order' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Policies / HR', 'slug' => 'policies-hr', 'description' => null, 'display_order' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_progress');
        Schema::dropIfExists('knowledge_base_assignments');
        Schema::dropIfExists('knowledge_base_items');
        Schema::dropIfExists('knowledge_base_categories');
    }
};
