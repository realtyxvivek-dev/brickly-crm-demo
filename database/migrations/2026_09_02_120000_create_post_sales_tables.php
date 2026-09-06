<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_sale_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_id')->unique()->constrained('site_visits')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('builder_id')->nullable()->constrained('builders')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('case_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('project_name')->nullable();
            $table->string('unit_label')->nullable();
            $table->date('booking_date')->nullable();
            $table->decimal('agreement_value', 15, 2)->default(0);
            $table->decimal('revenue_value', 15, 2)->default(0);
            $table->string('status')->default('handover_pending');
            $table->boolean('kyc_complete')->default(false);
            $table->boolean('needs_mapping')->default(false);
            $table->boolean('reminders_enabled')->default(false);
            $table->text('internal_remark')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'booking_date']);
            $table->index(['builder_id', 'project_id']);
        });

        Schema::create('post_sale_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_id')->nullable()->constrained('builders')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'version']);
        });

        Schema::create('post_sale_plan_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('post_sale_plan_templates')->cascadeOnDelete();
            $table->string('title');
            $table->string('amount_type')->default('percentage');
            $table->decimal('percentage', 7, 3)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->string('due_rule')->default('relative_days');
            $table->integer('relative_days')->nullable();
            $table->date('fixed_date')->nullable();
            $table->string('milestone_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('post_sale_demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_sale_case_id')->constrained('post_sale_cases')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('post_sale_plan_template_items')->nullOnDelete();
            $table->string('title');
            $table->string('amount_type')->default('percentage');
            $table->decimal('percentage', 7, 3)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('due_rule')->default('fixed_date');
            $table->date('due_date')->nullable();
            $table->string('milestone_name')->nullable();
            $table->string('status')->default('upcoming');
            $table->json('reminder_log')->nullable();
            $table->text('remark')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['due_date', 'status']);
        });

        Schema::create('post_sale_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_sale_case_id')->constrained('post_sale_cases')->cascadeOnDelete();
            $table->foreignId('demand_id')->nullable()->constrained('post_sale_demands')->nullOnDelete();
            $table->string('type')->default('payment');
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('payment_mode')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('remark')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['post_sale_case_id', 'status', 'type']);
        });

        Schema::create('post_sale_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_sale_case_id')->constrained('post_sale_cases')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['post_sale_case_id', 'document_type']);
        });

        Schema::create('builder_release_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_id')->nullable()->constrained('builders')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('builder_name');
            $table->string('project_name')->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->string('setup_status')->default('ready');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['builder_id', 'project_id', 'is_active']);
        });

        Schema::create('builder_release_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained('builder_release_schemes')->cascadeOnDelete();
            $table->decimal('customer_collection_percent', 7, 3);
            $table->decimal('brokerage_release_percent', 7, 3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['scheme_id', 'customer_collection_percent'], 'brs_scheme_collection_unique');
        });

        Schema::create('post_sale_case_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_sale_case_id')->constrained('post_sale_cases')->cascadeOnDelete();
            $table->foreignId('source_scheme_id')->nullable()->constrained('builder_release_schemes')->nullOnDelete();
            $table->decimal('customer_collection_percent', 7, 3);
            $table->decimal('brokerage_release_percent', 7, 3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['post_sale_case_id', 'customer_collection_percent'], 'pscs_case_collection_unique');
        });

        Schema::create('builder_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_sale_case_id')->constrained('post_sale_cases')->cascadeOnDelete();
            $table->decimal('collection_percent', 7, 3)->default(0);
            $table->decimal('release_percent', 7, 3)->default(0);
            $table->decimal('eligible_amount', 15, 2)->default(0);
            $table->decimal('claim_amount', 15, 2);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            $table->date('claim_date');
            $table->date('expected_date')->nullable();
            $table->string('status')->default('claim_raised');
            $table->string('proof_path')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'expected_date']);
        });

        Schema::create('builder_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_claim_id')->constrained('builder_claims')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('received_date');
            $table->string('payment_mode')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('builder_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_id')->nullable()->constrained('builders')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('draft');
            $table->unsignedInteger('revision_no')->default(0);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('seller_name');
            $table->text('seller_address')->nullable();
            $table->string('seller_gstin')->nullable();
            $table->string('buyer_name');
            $table->text('buyer_address')->nullable();
            $table->string('buyer_gstin')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            $table->decimal('net_receivable', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['builder_id', 'status', 'invoice_date']);
        });

        Schema::create('builder_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_invoice_id')->constrained('builder_invoices')->cascadeOnDelete();
            $table->foreignId('builder_claim_id')->nullable()->constrained('builder_claims')->nullOnDelete();
            $table->foreignId('post_sale_case_id')->nullable()->constrained('post_sale_cases')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('rate', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('builder_invoice_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_invoice_id')->constrained('builder_invoices')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->json('snapshot');
            $table->text('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['builder_invoice_id', 'revision_no']);
        });

        Schema::create('post_sale_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_sale_case_id')->nullable()->constrained('post_sale_cases')->cascadeOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action');
            $table->text('remark')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['post_sale_case_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_sale_activities');
        Schema::dropIfExists('builder_invoice_revisions');
        Schema::dropIfExists('builder_invoice_items');
        Schema::dropIfExists('builder_invoices');
        Schema::dropIfExists('builder_receipts');
        Schema::dropIfExists('builder_claims');
        Schema::dropIfExists('post_sale_case_slabs');
        Schema::dropIfExists('builder_release_slabs');
        Schema::dropIfExists('builder_release_schemes');
        Schema::dropIfExists('post_sale_documents');
        Schema::dropIfExists('post_sale_transactions');
        Schema::dropIfExists('post_sale_demands');
        Schema::dropIfExists('post_sale_plan_template_items');
        Schema::dropIfExists('post_sale_plan_templates');
        Schema::dropIfExists('post_sale_cases');
    }
};
