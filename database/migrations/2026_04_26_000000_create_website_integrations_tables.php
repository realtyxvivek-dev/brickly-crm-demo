<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('website');
            $table->string('default_status')->default('new');
            $table->string('api_key', 80)->unique();
            $table->text('description')->nullable();
            $table->string('fallback_type')->default('unassigned_crm_queue');
            $table->foreignId('fallback_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('allowed_domains')->nullable();
            $table->unsignedInteger('rate_limit')->default(60);
            $table->json('sample_payload_json')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('website_integration_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_integration_id')
                ->constrained(table: 'website_integrations', indexName: 'wi_field_mappings_integration_fk')
                ->cascadeOnDelete();
            $table->string('incoming_field');
            $table->string('crm_field')->nullable();
            $table->boolean('is_required')->default(false);
            $table->text('default_value')->nullable();
            $table->boolean('is_ignored')->default(false);
            $table->unsignedInteger('display_order')->default(1);
            $table->timestamps();
        });

        Schema::create('website_integration_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_integration_id')
                ->constrained(table: 'website_integrations', indexName: 'wi_request_logs_integration_fk')
                ->cascadeOnDelete();
            $table->string('request_id', 64)->index();
            $table->string('request_ip', 45)->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('mapped_payload')->nullable();
            $table->json('validation_result')->nullable();
            $table->json('assignment_result')->nullable();
            $table->json('fallback_result')->nullable();
            $table->string('status')->default('received')->index();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->boolean('duplicate')->default(false);
            $table->boolean('is_test')->default(false);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('website_integration_id')->nullable()->after('last_sent_meta_stage')->constrained('website_integrations')->nullOnDelete();
            $table->string('website_queue_status')->nullable()->after('website_integration_id');
            $table->json('website_payload_meta')->nullable()->after('website_queue_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('website_integration_id');
            $table->dropColumn(['website_queue_status', 'website_payload_meta']);
        });

        Schema::dropIfExists('website_integration_request_logs');
        Schema::dropIfExists('website_integration_field_mappings');
        Schema::dropIfExists('website_integrations');
    }
};
