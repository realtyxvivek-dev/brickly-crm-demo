<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApprovalLog;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PurchaseOrderApprovalNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->nullable();
            $table->string('po_number')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('vendor_name');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status');
            $table->unsignedBigInteger('admin_reviewed_by')->nullable();
            $table->timestamp('admin_reviewed_at')->nullable();
            $table->text('admin_remark')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_order_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('action');
            $table->unsignedBigInteger('action_by')->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('action_at');
            $table->timestamps();
        });
    }

    public function test_approval_notifies_creator_and_latest_submitter_without_duplicates(): void
    {
        $employeeRole = Role::create(['name' => 'Employee', 'slug' => 'employee']);
        $financeRole = Role::create(['name' => 'Finance Manager', 'slug' => Role::FINANCE_MANAGER]);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => Role::ADMIN]);

        $creator = $this->user($employeeRole, 'creator@example.com');
        $submitter = $this->user($financeRole, 'submitter@example.com');
        $admin = $this->user($adminRole, 'admin@example.com');
        $order = PurchaseOrder::create([
            'request_number' => 'POR-2026-0001',
            'created_by' => $creator->id,
            'vendor_name' => 'Test Vendor',
            'total_amount' => 1000,
            'status' => PurchaseOrder::STATUS_SUBMITTED,
        ]);
        PurchaseOrderApprovalLog::create([
            'purchase_order_id' => $order->id,
            'action' => 'submitted',
            'action_by' => $creator->id,
            'action_at' => now()->subMinute(),
        ]);
        PurchaseOrderApprovalLog::create([
            'purchase_order_id' => $order->id,
            'action' => 'resubmitted',
            'action_by' => $submitter->id,
            'action_at' => now(),
        ]);

        $notifiedIds = [];
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifyPurchaseOrderApproved')
            ->twice()
            ->andReturnUsing(function (User $user) use (&$notifiedIds) {
                $notifiedIds[] = $user->id;

                return new AppNotification();
            });
        $this->app->instance(NotificationService::class, $notifications);

        app(PurchaseOrderService::class)->approve($order, $admin);

        sort($notifiedIds);
        $expectedIds = [$creator->id, $submitter->id];
        sort($expectedIds);
        $this->assertSame($expectedIds, $notifiedIds);
        $this->assertSame(PurchaseOrder::STATUS_PAYMENT_PENDING, $order->fresh()->status);
    }

    private function user(Role $role, string $email): User
    {
        return User::create([
            'name' => $role->name,
            'email' => $email,
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
