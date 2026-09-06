<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisor_public_reviews', function (Blueprint $table) {
            $table->string('submission_source', 30)->default('public_form')->after('is_verified_customer');
            $table->string('content_type', 20)->default('text')->after('submission_source');
            $table->string('video_url')->nullable()->after('content_type');
            $table->string('video_platform', 30)->nullable()->after('video_url');
            $table->string('video_thumbnail_url')->nullable()->after('video_platform');
            $table->foreignId('submitted_by_user_id')->nullable()->after('video_thumbnail_url')->constrained('users')->nullOnDelete();
            $table->boolean('customer_consent_confirmed')->default(false)->after('submitted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_public_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn([
                'submission_source',
                'content_type',
                'video_url',
                'video_platform',
                'video_thumbnail_url',
                'customer_consent_confirmed',
            ]);
        });
    }
};
