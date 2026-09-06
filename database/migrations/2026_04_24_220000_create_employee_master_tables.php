<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_designations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code')->unique();
            $table->foreignId('department_id')->nullable()->constrained('employee_departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('employee_designations')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->string('employment_status')->default('active');
            $table->timestamp('employment_status_changed_at')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->unsignedTinyInteger('salary_day_of_month')->nullable()->default(1);
            $table->string('account_holder_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('ifsc_code', 30)->nullable();
            $table->string('pan_number', 30)->nullable();
            $table->string('aadhaar_number', 30)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('welcome_email_sent_at')->nullable();
            $table->timestamp('probation_alert_sent_at')->nullable();
            $table->timestamp('document_alert_sent_at')->nullable();
            $table->date('salary_reminder_sent_on')->nullable();
            $table->timestamps();
            $table->index(['employment_status', 'department_id']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_label');
            $table->string('document_number')->nullable();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_required')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_profile_id', 'document_type']);
        });

        Schema::create('employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type');
            $table->string('asset_name');
            $table->string('serial_number')->nullable();
            $table->string('vendor')->nullable();
            $table->string('asset_condition')->nullable();
            $table->string('status')->default('issued');
            $table->date('issued_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_profile_id', 'status']);
        });

        Schema::create('employee_asset_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta_json')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('meta_json')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('event_date')->nullable();
            $table->timestamps();
            $table->index(['employee_profile_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_timeline_events');
        Schema::dropIfExists('employee_asset_logs');
        Schema::dropIfExists('employee_assets');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('employee_designations');
        Schema::dropIfExists('employee_departments');
    }
};
