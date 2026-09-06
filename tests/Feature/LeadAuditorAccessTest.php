<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadAuditorAccessService;
use App\Services\LeadAuditorDashboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LeadAuditorAccessTest extends LeadAuditorDashboardTest
{
    private function auditor(): LeadAuditorAccessService
    {
        Schema::create('lead_auditor_user_access', function (Blueprint $t) {
            $t->integer('auditor_id'); $t->integer('sales_user_id'); $t->timestamps();
        });
        DB::table('roles')->insert(['id' => 2, 'slug' => 'lead_quality_auditor']);
        DB::table('users')->insert(['id' => 3, 'name' => 'Auditor', 'role_id' => 2]);
        $this->actingAs(User::findOrFail(3));
        return app(LeadAuditorAccessService::class);
    }

    public function test_empty_scope_denies_and_selection_takes_effect_without_new_session(): void
    {
        $access = $this->auditor();
        $this->assertSame([], $access->allowedIds());
        $this->assertSame(0, $access->scopeLeads(Lead::query())->count());
        $this->assertSame(0, app(LeadAuditorDashboardService::class)->summary()['untouched']['total']);
        DB::table('lead_auditor_user_access')->insert(['auditor_id' => 3, 'sales_user_id' => 1]);
        $this->assertEquals([1], $access->allowedIds());
        $this->assertSame(1, $access->scopeLeads(Lead::query())->count());
        $this->assertSame(1, app(LeadAuditorDashboardService::class)->summary()['untouched']['total']);
        DB::table('lead_auditor_user_access')->delete();
        $this->assertSame(0, $access->scopeLeads(Lead::query())->count());
    }

    public function test_latest_primary_assignment_controls_access_and_excludes_old_owner_tasks(): void
    {
        $access = $this->auditor();
        DB::table('lead_auditor_user_access')->insert(['auditor_id' => 3, 'sales_user_id' => 1]);
        DB::table('tasks')->insert(['lead_id' => 1, 'assigned_to' => 1, 'type' => 'phone_call', 'scheduled_at' => '2026-09-04 10:00:00']);
        $dashboard = app(LeadAuditorDashboardService::class);
        $this->assertSame(1, $dashboard->drilldown(1, 'due', null)->count());
        $id = DB::table('lead_assignments')->insertGetId(['lead_id' => 1, 'assigned_to' => 2, 'assigned_at' => '2026-09-04 09:00:00']);
        $this->assertSame(0, $access->scopeLeads(Lead::query())->count());
        $this->assertSame(0, $dashboard->drilldown(1, 'due', null)->count());
        DB::table('lead_assignments')->where('id', $id)->update(['assignment_type' => 'secondary']);
        $this->assertSame(1, $access->scopeLeads(Lead::query())->count());
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->assertSame(0, $access->scopeLeads(Lead::query())->count());
    }

    public function test_guessed_user_and_lead_ids_are_denied(): void
    {
        $access = $this->auditor();
        foreach ([fn () => $access->authorizeUser(1), fn () => $access->authorizeLead(1), fn () => $access->authorizeRow('lead:1')] as $operation) {
            try { $operation(); $this->fail('Expected denied access'); }
            catch (HttpException $e) { $this->assertContains($e->getStatusCode(), [403, 404]); }
        }
        DB::table('roles')->insert(['id' => 4, 'slug' => 'admin']);
        DB::table('users')->insert(['id' => 4, 'name' => 'Admin', 'role_id' => 4]);
        $this->actingAs(User::findOrFail(4));
        $this->assertSame(1, $access->scopeLeads(Lead::query())->count());
    }

    public function test_direct_auditor_detail_and_mutation_endpoints_deny_outside_scope(): void
    {
        $this->auditor();
        foreach (['admin.insight-sheet.details', 'admin.insight-sheet.remarks', 'admin.insight-sheet.completion-details', 'admin.insight-sheet.audit'] as $route) {
            $this->getJson(route($route, ['row_key' => 'lead:1', 'column_key' => 'internal_remark']))->assertNotFound();
        }
        $this->getJson(route('lead-quality-auditor.dashboard.details', ['lead_id' => 1]))->assertNotFound();
        $this->getJson(route('lead-quality-auditor.dashboard.tasks', ['user_id' => 1, 'bucket' => 'due']))->assertForbidden();
        $this->postJson(route('admin.insight-sheet.cells.bulk-save'), ['cells' => [['row_key' => 'lead:1', 'column_key' => 'internal_remark', 'value' => 'Denied']]])->assertNotFound();
        $this->postJson(route('admin.insight-sheet.details.override'), ['row_key' => 'lead:1', 'values' => ['budget' => 'Denied']])->assertNotFound();
        $this->postJson(route('lead-quality-auditor.lead-off.update'), ['user_id' => 1, 'is_absent' => true])->assertForbidden();
    }

    public function test_selection_validation_and_audit_preserve_admin_only_access(): void
    {
        $access = $this->auditor();
        $auditor = User::findOrFail(3);
        $request = \Illuminate\Http\Request::create('/', 'POST', ['auditor_user_ids' => [1]]);
        $request->setUserResolver(fn () => $auditor);
        try { $access->validateSelection($request, $auditor, 'lead_quality_auditor'); $this->fail('Self grant accepted'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        DB::table('roles')->insert(['id' => 4, 'slug' => 'admin']);
        DB::table('users')->insert(['id' => 4, 'name' => 'Admin', 'role_id' => 4]);
        $request->setUserResolver(fn () => User::findOrFail(4));
        $this->assertSame([1], $access->validateSelection($request, $auditor, 'lead_quality_auditor'));
        $this->assertSame([], $access->validateSelection($request, $auditor, 'sales_manager'));
        $request->merge(['auditor_user_ids' => [3]]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $access->validateSelection($request, $auditor, 'lead_quality_auditor');
    }

    public function test_admin_can_persist_clear_and_audit_scope_and_render_picker(): void
    {
        $access = $this->auditor();
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('action'); $t->string('model_type'); $t->integer('model_id');
            $t->text('description'); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->string('ip_address')->nullable(); $t->timestamps();
        });
        Schema::table('roles', fn (Blueprint $t) => $t->string('name')->nullable());
        DB::table('roles')->insert(['id' => 4, 'slug' => 'admin', 'name' => 'Admin']);
        DB::table('users')->insert(['id' => 4, 'name' => 'Admin', 'role_id' => 4]);
        $admin = User::findOrFail(4);
        $this->actingAs($admin);
        $request = \Illuminate\Http\Request::create('/', 'POST');
        $request->setUserResolver(fn () => $admin);
        $auditor = User::findOrFail(3);
        DB::transaction(fn () => $access->sync($auditor, [1, 2], $request));
        $this->assertEquals([1, 2], $access->selectedIds($auditor));
        $this->assertSame(1, DB::table('activity_logs')->count());
        $html = view('users.partials.auditor-access', ['user' => $auditor, 'currentRoleSlug' => 'lead_quality_auditor', 'errors' => new \Illuminate\Support\ViewErrorBag()])->render();
        $this->assertStringContainsString('Allowed Sales Users', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('readonly data-1p-ignore', $html);
        $this->assertStringContainsString('No matching sales users found.', $html);
        $this->assertStringContainsString('value="1" checked', $html);
        $this->assertStringContainsString('value="2" checked', $html);
        DB::transaction(fn () => $access->sync($auditor, [], $request));
        $this->assertSame([], $access->selectedIds($auditor));
        $this->assertSame(2, DB::table('activity_logs')->count());
    }
}
