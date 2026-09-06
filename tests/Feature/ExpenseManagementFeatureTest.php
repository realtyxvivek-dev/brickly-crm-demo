<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use App\Models\ExpenseSubcategory;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseDashboardSummaryService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseManagementFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('filesystems.disks.public.root', base_path('tests/tmp/public'));

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        File::ensureDirectoryExists(base_path('tests/tmp/public'));

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 20)->default('text');
            $table->string('category', 50);
            $table->string('group', 50)->nullable();
            $table->string('display_label', 255)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->text('validation_rules')->nullable();
            $table->text('help_text')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->foreignId('role_id')->nullable();
            $table->foreignId('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('two_factor_mode')->nullable();
            $table->boolean('two_factor_enforced_by_admin')->default(false);
            $table->boolean('otp_recovery_allowed')->default(false);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('name');
            $table->string('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        DB::table('expense_payment_methods')->insert([
            ['type' => 'cash', 'name' => 'Cash', 'sort_order' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'bank', 'name' => 'Bank Account', 'sort_order' => 20, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'upi', 'name' => 'UPI Account', 'sort_order' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'credit_card', 'name' => 'Credit Card', 'sort_order' => 40, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->foreignId('expense_subcategory_id')->constrained('expense_subcategories');
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_mode', 20);
            $table->foreignId('expense_payment_method_id')->nullable()->constrained('expense_payment_methods');
            $table->string('paid_to')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('reject_reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('approval_assigned_to')->nullable()->constrained('users');
            $table->foreignId('approval_assigned_by')->nullable()->constrained('users');
            $table->timestamp('approval_assigned_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('expense_entries');
        Schema::dropIfExists('expense_payment_methods');
        Schema::dropIfExists('expense_subcategories');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        parent::tearDown();

        File::deleteDirectory(base_path('tests/tmp/public'));
    }

    public function test_admin_can_create_expense_entry(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        $approver = $this->createAdminApprover('expense-approver@example.test');
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $response = $this->actingAs($admin)->post(route('admin.expenses.entries.store'), [
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '2500',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'approval_assigned_to' => $approver->id,
            'paid_to' => 'UPPCL',
            'reference_no' => 'TXN-12345',
            'remarks' => 'Monthly electricity bill',
            'attachment_path' => null,
        ]);

        $response->assertRedirect(route('admin.expenses.queue'));

        $this->assertDatabaseHas('expense_entries', [
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'paid_to' => 'UPPCL',
            'reference_no' => 'TXN-12345',
            'status' => ExpenseEntry::STATUS_DRAFT,
            'approval_assigned_to' => $approver->id,
            'approval_assigned_by' => $admin->id,
        ]);
    }

    public function test_subcategory_must_belong_to_selected_category(): void
    {
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        $approver = $this->createAdminApprover('finance-approver@example.test');
        [$company, $category] = $this->createExpenseMasters();
        $otherCategory = ExpenseCategory::create([
            'name' => 'Travel',
            'code' => 'TRAVEL',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $wrongSubcategory = ExpenseSubcategory::create([
            'expense_category_id' => $otherCategory->id,
            'name' => 'Fuel',
            'code' => 'FUEL',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($finance)
            ->from(route('finance-manager.expenses.entries.create'))
            ->post(route('finance-manager.expenses.entries.store'), [
                'company_id' => $company->id,
                'expense_category_id' => $category->id,
                'expense_subcategory_id' => $wrongSubcategory->id,
                'expense_date' => '2026-04-25',
                'amount' => '1200',
                'payment_mode' => ExpenseEntry::PAYMENT_MODE_CASH,
                'approval_assigned_to' => $approver->id,
            ]);

        $response->assertRedirect(route('finance-manager.expenses.entries.create'));
        $response->assertSessionHasErrors('expense_subcategory_id');
    }

    public function test_finance_manager_can_view_expense_pages(): void
    {
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);

        $response = $this->actingAs($finance)->get(route('finance-manager.expenses.entries.index'));

        $response->assertOk();
        $response->assertSee('Expense');
    }

    public function test_expense_dashboard_summary_respects_selected_date_range(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        foreach ([
            ['date' => '2026-06-22', 'amount' => 1250],
            ['date' => '2026-06-10', 'amount' => 3750],
            ['date' => '2026-05-31', 'amount' => 9000],
        ] as $expense) {
            ExpenseEntry::create([
                'company_id' => $company->id,
                'expense_category_id' => $category->id,
                'expense_subcategory_id' => $subcategory->id,
                'expense_date' => $expense['date'],
                'amount' => $expense['amount'],
                'payment_mode' => ExpenseEntry::PAYMENT_MODE_BANK,
                'status' => ExpenseEntry::STATUS_APPROVED,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        $service = app(ExpenseDashboardSummaryService::class);
        $today = $service->getRangeSnapshot(
            Carbon::parse('2026-06-22')->startOfDay(),
            Carbon::parse('2026-06-22')->endOfDay()
        );
        $month = $service->getMonthSnapshot(2026, 6);

        $this->assertSame(1, $today['summary']['entry_count']);
        $this->assertSame(1250.0, $today['summary']['total_amount']);
        $this->assertSame(2, $month['summary']['entry_count']);
        $this->assertSame(5000.0, $month['summary']['total_amount']);
    }

    public function test_admin_can_upload_attachment_with_expense_entry(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        $approver = $this->createAdminApprover('upload-approver@example.test');
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $response = $this->actingAs($admin)->post(route('admin.expenses.entries.store'), [
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '3400',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_BANK,
            'approval_assigned_to' => $approver->id,
            'paid_to' => 'Meta',
            'reference_no' => 'INV-908',
            'remarks' => 'Campaign invoice',
            'attachment' => UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('admin.expenses.queue'));

        $entry = ExpenseEntry::query()->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertNotNull($entry->attachment_path);
        Storage::disk('public')->assertExists($entry->attachment_path);
    }

    public function test_expense_export_returns_csv(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '2500',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'paid_to' => 'UPPCL',
            'reference_no' => 'TXN-12345',
            'remarks' => 'Monthly electricity bill',
            'status' => ExpenseEntry::STATUS_APPROVED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expenses.entries.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Date,Company,Category,Subcategory,Amount', $response->streamedContent());
        $this->assertStringContainsString('UPPCL', $response->streamedContent());
    }

    public function test_finance_manager_can_open_monthly_report_and_summary_export(): void
    {
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '4100',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_BANK,
            'paid_to' => 'Rahul',
            'reference_no' => 'SAL-25',
            'remarks' => 'Salary payout',
            'status' => ExpenseEntry::STATUS_APPROVED,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $reportResponse = $this->actingAs($finance)->get(route('finance-manager.expenses.monthly-report', [
            'year' => 2026,
            'month' => 4,
        ]));

        $reportResponse->assertOk();
        $reportResponse->assertSee('Monthly Report');
        $reportResponse->assertSee('BIH Infra', false);
        $reportResponse->assertSee('Electricity', false);

        $exportResponse = $this->actingAs($finance)->get(route('finance-manager.expenses.summary.export', [
            'year' => 2026,
            'month' => 4,
        ]));

        $exportResponse->assertOk();
        $normalizedCsv = str_replace('"', '', $exportResponse->streamedContent());
        $this->assertStringContainsString('Company,Category,Subcategory,Status,Entry Count,Total Amount', $normalizedCsv);
        $this->assertStringContainsString('BIH Infra,Electricity,UPPCL,approved,1,4100', $normalizedCsv);
    }

    public function test_finance_manager_can_view_approval_queue_and_approve_draft_expense(): void
    {
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        $approver = $this->createAdminApprover('queue-approver@example.test');
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $entry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '1800',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_CASH,
            'paid_to' => 'Vendor A',
            'reference_no' => 'REF-18',
            'remarks' => 'Draft expense',
            'status' => ExpenseEntry::STATUS_DRAFT,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
            'approval_assigned_to' => $approver->id,
            'approval_assigned_by' => $finance->id,
            'approval_assigned_at' => now(),
        ]);

        $queueResponse = $this->actingAs($finance)->get(route('finance-manager.expenses.queue'));
        $queueResponse->assertOk();
        $queueResponse->assertSee('Draft Approval Queue');
        $queueResponse->assertSee('Vendor A');

        $approveResponse = $this->actingAs($finance)->post(route('finance-manager.expenses.entries.approve', $entry));
        $approveResponse->assertRedirect();

        $this->assertDatabaseHas('expense_entries', [
            'id' => $entry->id,
            'status' => ExpenseEntry::STATUS_APPROVED,
            'approved_by' => $finance->id,
            'reject_reason' => null,
        ]);

        $this->assertNotNull($entry->fresh()->approved_at);
    }

    public function test_admin_can_reject_draft_expense_with_reason(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        $approver = $this->createAdminApprover('reject-approver@example.test');
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $entry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '999',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'paid_to' => 'Duplicate Vendor',
            'reference_no' => 'DUP-1',
            'remarks' => 'To be rejected',
            'status' => ExpenseEntry::STATUS_DRAFT,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
            'approval_assigned_to' => $approver->id,
            'approval_assigned_by' => $finance->id,
            'approval_assigned_at' => now(),
        ]);

        $missingReasonResponse = $this->actingAs($admin)
            ->from(route('admin.expenses.queue'))
            ->post(route('admin.expenses.entries.reject', $entry), []);

        $missingReasonResponse->assertRedirect(route('admin.expenses.queue'));
        $missingReasonResponse->assertSessionHasErrors('reject_reason');

        $rejectResponse = $this->actingAs($admin)->post(route('admin.expenses.entries.reject', $entry), [
            'reject_reason' => 'Duplicate expense found',
        ]);

        $rejectResponse->assertRedirect();

        $this->assertDatabaseHas('expense_entries', [
            'id' => $entry->id,
            'status' => ExpenseEntry::STATUS_REJECTED,
            'reject_reason' => 'Duplicate expense found',
            'rejected_by' => $admin->id,
        ]);

        $this->assertNotNull($entry->fresh()->rejected_at);
    }

    public function test_rejected_expense_is_visible_to_finance_manager_with_reject_reason(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        $approver = $this->createAdminApprover('visible-approver@example.test');
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $entry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '999',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'paid_to' => 'Duplicate Vendor',
            'reference_no' => 'DUP-1',
            'remarks' => 'To be rejected',
            'status' => ExpenseEntry::STATUS_REJECTED,
            'reject_reason' => 'Duplicate expense found',
            'created_by' => $finance->id,
            'updated_by' => $admin->id,
            'approval_assigned_to' => $approver->id,
            'approval_assigned_by' => $finance->id,
            'approval_assigned_at' => now(),
            'rejected_by' => $admin->id,
            'rejected_at' => now(),
        ]);

        $response = $this->actingAs($finance)->get(route('finance-manager.expenses.entries.index'));

        $response->assertOk();
        $response->assertSee('Rejected');
        $response->assertSee('Duplicate expense found');
        $response->assertSee('Edit & Resubmit');
        $response->assertSee($admin->name);
        $response->assertSee($approver->name);
        $this->assertDatabaseHas('expense_entries', [
            'id' => $entry->id,
            'status' => ExpenseEntry::STATUS_REJECTED,
        ]);
    }

    public function test_finance_manager_can_resubmit_own_rejected_expense(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        $originalApprover = $this->createAdminApprover('original-approver@example.test');
        $newApprover = $this->createAdminApprover('new-approver@example.test');
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $entry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '999',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'paid_to' => 'Duplicate Vendor',
            'reference_no' => 'DUP-1',
            'remarks' => 'To be rejected',
            'status' => ExpenseEntry::STATUS_REJECTED,
            'reject_reason' => 'Duplicate expense found',
            'created_by' => $finance->id,
            'updated_by' => $admin->id,
            'approval_assigned_to' => $originalApprover->id,
            'approval_assigned_by' => $finance->id,
            'approval_assigned_at' => now()->subDay(),
            'rejected_by' => $admin->id,
            'rejected_at' => now(),
        ]);

        $response = $this->actingAs($finance)->put(route('finance-manager.expenses.entries.update', $entry), [
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-26',
            'amount' => '1299',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_BANK,
            'approval_assigned_to' => $newApprover->id,
            'paid_to' => 'Corrected Vendor',
            'reference_no' => 'DUP-1-FIX',
            'remarks' => 'Corrected and resubmitted',
            'existing_attachment_path' => null,
        ]);

        $response->assertRedirect(route('finance-manager.expenses.entries.index'));
        $response->assertSessionHas('success', 'Expense updated and resubmitted to ' . $newApprover->name . ' for approval.');

        $this->assertDatabaseHas('expense_entries', [
            'id' => $entry->id,
            'status' => ExpenseEntry::STATUS_DRAFT,
            'reject_reason' => null,
            'rejected_by' => null,
            'approved_by' => null,
            'approval_assigned_to' => $newApprover->id,
            'approval_assigned_by' => $finance->id,
            'paid_to' => 'Corrected Vendor',
            'reference_no' => 'DUP-1-FIX',
        ]);

        $this->assertNull($entry->fresh()->rejected_at);
    }

    public function test_other_finance_manager_cannot_edit_rejected_expense_of_another_creator(): void
    {
        $admin = $this->createUserForRole(Role::ADMIN);
        $owner = $this->createUserForRole(Role::FINANCE_MANAGER);
        $approver = $this->createAdminApprover('locked-approver@example.test');
        $otherFinance = User::create([
            'name' => 'Peer Finance User',
            'email' => 'finance-peer@example.test',
            'password' => Hash::make('secret123'),
            'phone' => '8888888888',
            'role_id' => $owner->role_id,
            'is_active' => true,
        ]);

        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $entry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '999',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'paid_to' => 'Duplicate Vendor',
            'reference_no' => 'DUP-1',
            'remarks' => 'To be rejected',
            'status' => ExpenseEntry::STATUS_REJECTED,
            'reject_reason' => 'Duplicate expense found',
            'created_by' => $owner->id,
            'updated_by' => $admin->id,
            'approval_assigned_to' => $approver->id,
            'approval_assigned_by' => $owner->id,
            'approval_assigned_at' => now(),
            'rejected_by' => $admin->id,
            'rejected_at' => now(),
        ]);

        $response = $this->actingAs($otherFinance)->get(route('finance-manager.expenses.entries.edit', $entry));

        $response->assertForbidden();
    }

    public function test_finance_manager_must_choose_admin_approver_when_creating_expense(): void
    {
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $response = $this->actingAs($finance)
            ->from(route('finance-manager.expenses.entries.create'))
            ->post(route('finance-manager.expenses.entries.store'), [
                'company_id' => $company->id,
                'expense_category_id' => $category->id,
                'expense_subcategory_id' => $subcategory->id,
                'expense_date' => '2026-04-25',
                'amount' => '2200',
                'payment_mode' => ExpenseEntry::PAYMENT_MODE_CASH,
                'paid_to' => 'Vendor A',
            ]);

        $response->assertRedirect(route('finance-manager.expenses.entries.create'));
        $response->assertSessionHasErrors('approval_assigned_to');
    }

    public function test_admin_queue_can_filter_by_assigned_scope_and_approver(): void
    {
        $admin = $this->createAdminApprover('admin-a@example.test');
        $otherAdmin = $this->createAdminApprover('admin-b@example.test');
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $mineEntry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '1800',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_BANK,
            'paid_to' => 'Mine Vendor',
            'status' => ExpenseEntry::STATUS_DRAFT,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
            'approval_assigned_to' => $admin->id,
            'approval_assigned_by' => $finance->id,
            'approval_assigned_at' => now(),
        ]);

        $otherEntry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '2100',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'paid_to' => 'Other Vendor',
            'status' => ExpenseEntry::STATUS_DRAFT,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
            'approval_assigned_to' => $otherAdmin->id,
            'approval_assigned_by' => $finance->id,
            'approval_assigned_at' => now(),
        ]);

        $mineResponse = $this->actingAs($admin)->get(route('admin.expenses.queue', [
            'assigned_scope' => 'mine',
        ]));
        $mineResponse->assertOk();
        $mineResponse->assertSee($mineEntry->paid_to);
        $mineResponse->assertDontSee($otherEntry->paid_to);

        $approverResponse = $this->actingAs($admin)->get(route('admin.expenses.queue', [
            'approval_assigned_to' => $otherAdmin->id,
        ]));
        $approverResponse->assertOk();
        $approverResponse->assertSee($otherEntry->paid_to);
        $approverResponse->assertDontSee($mineEntry->paid_to);
    }

    public function test_non_assigned_admin_can_still_approve_visible_draft_expense(): void
    {
        $assignedAdmin = $this->createAdminApprover('assigned-admin@example.test');
        $actingAdmin = $this->createAdminApprover('acting-admin@example.test');
        $finance = $this->createUserForRole(Role::FINANCE_MANAGER);
        [$company, $category, $subcategory] = $this->createExpenseMasters();

        $entry = ExpenseEntry::create([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'expense_date' => '2026-04-25',
            'amount' => '500',
            'payment_mode' => ExpenseEntry::PAYMENT_MODE_UPI,
            'paid_to' => 'Shared Queue Vendor',
            'status' => ExpenseEntry::STATUS_DRAFT,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
            'approval_assigned_to' => $assignedAdmin->id,
            'approval_assigned_by' => $finance->id,
            'approval_assigned_at' => now(),
        ]);

        $response = $this->actingAs($actingAdmin)->post(route('admin.expenses.entries.approve', $entry));

        $response->assertRedirect();
        $this->assertDatabaseHas('expense_entries', [
            'id' => $entry->id,
            'status' => ExpenseEntry::STATUS_APPROVED,
            'approved_by' => $actingAdmin->id,
            'approval_assigned_to' => $assignedAdmin->id,
        ]);
    }

    private function createUserForRole(string $slug): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'is_active' => true,
            ]
        );

        return User::create([
            'name' => $slug . ' User',
            'email' => $slug . '@example.test',
            'password' => Hash::make('secret123'),
            'phone' => '9999999999',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createExpenseMasters(): array
    {
        $company = Company::create([
            'name' => 'BIH Infra',
            'code' => 'BIH_INFRA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $category = ExpenseCategory::create([
            'name' => 'Electricity',
            'code' => 'ELECTRICITY',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'name' => 'UPPCL',
            'code' => 'UPPCL',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$company, $category, $subcategory];
    }

    private function createAdminApprover(string $email): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Admin', 'is_active' => true]
        );

        return User::create([
            'name' => strstr($email, '@', true),
            'email' => $email,
            'password' => Hash::make('secret123'),
            'phone' => '7777777777',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
