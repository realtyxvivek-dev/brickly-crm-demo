<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_public_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('public_slug')->unique();
            $table->string('designation')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedInteger('experience_years')->nullable();
            $table->json('languages')->nullable();
            $table->json('service_areas')->nullable();
            $table->json('specialization_tags')->nullable();
            $table->text('why_choose_me')->nullable();
            $table->unsignedInteger('successful_closures')->default(0);
            $table->unsignedInteger('site_visits_handled')->default(0);
            $table->unsignedInteger('sqft_sold')->default(0);
            $table->unsignedInteger('happy_families_served')->default(0);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamps();
        });

        Schema::create('advisor_public_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_public_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_phone_masked');
            $table->unsignedTinyInteger('rating');
            $table->text('review_text');
            $table->string('project_name')->nullable();
            $table->boolean('is_verified_customer')->default(false);
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('advisor_public_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_public_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path');
            $table->string('caption')->nullable();
            $table->string('category')->nullable();
            $table->boolean('customer_consent_confirmed')->default(false);
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_public_gallery_items');
        Schema::dropIfExists('advisor_public_reviews');
        Schema::dropIfExists('advisor_public_profiles');
    }
};
