<?php

namespace Tests\Feature;

use App\Models\CallingCenterCampaign;
use App\Models\CallingCenterCampaignItem;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\McubeOutboundAttempt;
use App\Models\McubeSetting;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\UserPhonePrivacySetting;
use App\Services\AutoAssignmentMcubeCallService;
use App\Services\CallingCenterService;
use App\Services\McubeOutboundCallService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class McubeOutboundCallingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('broadcasting.default', 'log');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_outbound_click_to_call_posts_mcube_payload_and_logs_attempt(): void
    {
        Http::fake(['https://api.mcube.com/*' => Http::response(['message' => 'accepted'], 200)]);

        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'token-123',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
        ]);

        $user = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['phone' => '09876543210']);
        $lead = Lead::create(['name' => 'Buyer', 'phone' => '+91 91234 56789', 'source' => 'meta', 'status' => 'new']);

        $result = app(McubeOutboundCallService::class)->initiate($user, $lead);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('mcube_outbound_attempts', [
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'agent_number' => '9876543210',
            'customer_number' => '9123456789',
            'status' => 'success',
        ]);

        Http::assertSent(fn ($request) =>
            $request['HTTP_AUTHORIZATION'] === 'token-123'
            && $request['exenumber'] === '9876543210'
            && $request['custnumber'] === '9123456789'
            && filled($request['refid'])
        );
    }

    public function test_auto_assignment_call_is_default_off_and_opt_in(): void
    {
        Http::fake(['https://api.mcube.com/*' => Http::response(['message' => 'accepted'], 200)]);

        $role = $this->createRole(Role::SALES_EXECUTIVE);
        $user = $this->createUser($role, ['phone' => '9876543210']);
        $lead = Lead::create(['name' => 'Assigned Lead', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'assigned_by' => 1,
            'assignment_type' => 'primary',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'token-123',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
            'auto_call_on_assignment' => false,
        ]);

        $service = app(AutoAssignmentMcubeCallService::class);
        $this->assertNull($service->handle($lead, $user, 1));
        $this->assertSame(0, McubeOutboundAttempt::count());

        McubeSetting::query()->first()->update(['auto_call_on_assignment' => true]);

        $result = $service->handle($lead->fresh(), $user->fresh(), 1);
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('mcube_outbound_attempts', [
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'refid' => 'auto_assignment:lead:' . $lead->id . ':assignment:1',
        ]);
    }

    public function test_calling_center_cnp_retry_requeues_item_until_max_retries(): void
    {
        McubeSetting::create(['outbound_enabled' => false]);

        $admin = $this->createUser($this->createRole(Role::ADMIN), ['phone' => '9999999999']);
        $agent = $this->createUser($this->createRole(Role::TELECALLER), ['phone' => '9876543210']);
        $lead = Lead::create(['name' => 'Retry Lead', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        $task = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $agent->id,
            'type' => 'phone_call',
            'title' => 'Call',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $campaign = CallingCenterCampaign::create([
            'name' => 'Retry Campaign',
            'assigned_to' => $agent->id,
            'created_by' => $admin->id,
            'status' => CallingCenterCampaign::STATUS_RUNNING,
            'delay_seconds' => 120,
            'retry_policy' => 'cnp_no_answer',
            'max_retries' => 1,
        ]);
        $item = CallingCenterCampaignItem::create([
            'campaign_id' => $campaign->id,
            'lead_id' => $lead->id,
            'task_id' => $task->id,
            'phone' => '9123456789',
            'status' => CallingCenterCampaignItem::STATUS_OUTCOME_PENDING,
            'attempt_count' => 1,
        ]);

        app(CallingCenterService::class)->submitOutcome($item, $agent, ['outcome' => 'cnp']);

        $this->assertDatabaseHas('calling_center_campaign_items', [
            'id' => $item->id,
            'status' => CallingCenterCampaignItem::STATUS_PENDING,
            'outcome' => 'cnp',
        ]);
        $this->assertSame('10:02', $item->fresh()->next_call_at->format('H:i'));

        $item->fresh()->update([
            'status' => CallingCenterCampaignItem::STATUS_OUTCOME_PENDING,
            'attempt_count' => 2,
        ]);

        app(CallingCenterService::class)->submitOutcome($item->fresh(), $agent, ['outcome' => 'cnp']);

        $this->assertDatabaseHas('calling_center_campaign_items', [
            'id' => $item->id,
            'status' => CallingCenterCampaignItem::STATUS_COMPLETED,
            'outcome' => 'cnp',
        ]);
    }

    public function test_calling_center_campaign_uses_only_lead_bank_imported_leads(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['phone' => '9999999999']);
        $agent = $this->createUser($this->createRole(Role::TELECALLER), ['phone' => '9876543210']);
        $leadBankLead = Lead::create(['name' => 'Lead Bank Buyer', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        $normalLead = Lead::create(['name' => 'Normal Buyer', 'phone' => '9123456790', 'source' => 'meta', 'status' => 'new']);
        $this->markAsLeadBankLead($leadBankLead, $admin);

        $campaign = app(CallingCenterService::class)->createCampaign($admin, [
            'name' => 'Lead Bank Campaign',
            'assigned_to' => $agent->id,
            'folder_type' => 'system',
            'folder_key' => 'all',
            'quantity' => 10,
            'delay_seconds' => 120,
            'retry_policy' => 'none',
            'max_retries' => 0,
        ]);

        $this->assertDatabaseHas('calling_center_campaign_items', [
            'campaign_id' => $campaign->id,
            'lead_id' => $leadBankLead->id,
            'status' => CallingCenterCampaignItem::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('calling_center_campaign_items', [
            'campaign_id' => $campaign->id,
            'lead_id' => $normalLead->id,
        ]);
    }

    public function test_calling_center_tag_folder_filters_lead_bank_leads(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['phone' => '9999999999']);
        $agent = $this->createUser($this->createRole(Role::TELECALLER), ['phone' => '9876543210']);
        $folder = DB::table('lead_tags')->insertGetId([
            'name' => 'Hot Folder',
            'slug' => 'hot-folder',
            'type' => 'campaign',
            'color' => '#205A44',
            'is_folder' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inFolder = Lead::create(['name' => 'Folder Buyer', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        $outsideFolder = Lead::create(['name' => 'Outside Buyer', 'phone' => '9123456790', 'source' => 'meta', 'status' => 'new']);
        $this->markAsLeadBankLead($inFolder, $admin);
        $this->markAsLeadBankLead($outsideFolder, $admin);
        DB::table('lead_tag_assignments')->insert([
            'lead_id' => $inFolder->id,
            'lead_tag_id' => $folder,
            'assigned_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaign = app(CallingCenterService::class)->createCampaign($admin, [
            'name' => 'Folder Campaign',
            'assigned_to' => $agent->id,
            'folder_type' => 'tag',
            'folder_tag_id' => $folder,
            'quantity' => 10,
            'delay_seconds' => 120,
            'retry_policy' => 'none',
            'max_retries' => 0,
        ]);

        $this->assertDatabaseHas('calling_center_campaign_items', [
            'campaign_id' => $campaign->id,
            'lead_id' => $inFolder->id,
        ]);
        $this->assertDatabaseMissing('calling_center_campaign_items', [
            'campaign_id' => $campaign->id,
            'lead_id' => $outsideFolder->id,
        ]);
    }

    public function test_calling_center_selected_leads_are_intersected_with_lead_bank_filters(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['phone' => '9999999999']);
        $agent = $this->createUser($this->createRole(Role::TELECALLER), ['phone' => '9876543210']);
        $leadBankLead = Lead::create(['name' => 'Selected Lead Bank', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        $normalLead = Lead::create(['name' => 'Selected Normal', 'phone' => '9123456790', 'source' => 'meta', 'status' => 'new']);
        $this->markAsLeadBankLead($leadBankLead, $admin);

        $campaign = app(CallingCenterService::class)->createCampaign($admin, [
            'name' => 'Selected Campaign',
            'assigned_to' => $agent->id,
            'folder_type' => 'system',
            'folder_key' => 'all',
            'lead_ids' => [$leadBankLead->id, $normalLead->id],
            'quantity' => 10,
            'delay_seconds' => 120,
            'retry_policy' => 'none',
            'max_retries' => 0,
        ]);

        $this->assertDatabaseHas('calling_center_campaign_items', [
            'campaign_id' => $campaign->id,
            'lead_id' => $leadBankLead->id,
        ]);
        $this->assertDatabaseMissing('calling_center_campaign_items', [
            'campaign_id' => $campaign->id,
            'lead_id' => $normalLead->id,
        ]);
    }

    public function test_calling_center_agent_index_redirects_to_own_queue(): void
    {
        $agent = $this->createUser($this->createRole(Role::TELECALLER), ['phone' => '9876543210']);

        $this->actingAs($agent)
            ->get(route('calling-center.index'))
            ->assertRedirect(route('calling-center.queue'));
    }

    public function test_click_to_call_blocks_duplicate_manual_attempts_for_same_user_and_lead(): void
    {
        Http::fake(['https://api.mcube.com/*' => Http::response(['message' => 'accepted'], 200)]);

        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'token-123',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
        ]);

        $user = $this->createUser($this->createRole(Role::ADMIN), ['phone' => '9876543210']);
        $lead = Lead::create(['name' => 'Duplicate Lead', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        $controller = app(\App\Http\Controllers\Api\McubeOutboundCallController::class);

        $firstRequest = Request::create('/api/mcube/outbound-call', 'POST', ['lead_id' => $lead->id]);
        $firstRequest->setUserResolver(fn () => $user);
        $this->assertSame(200, $controller->store($firstRequest)->getStatusCode());

        $secondRequest = Request::create('/api/mcube/outbound-call', 'POST', ['lead_id' => $lead->id]);
        $secondRequest->setUserResolver(fn () => $user);
        $response = $controller->store($secondRequest);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['fallback_to_tel']);
    }

    public function test_sales_manager_manual_dialer_allows_assistant_sales_manager_and_logs_phone_only_attempt(): void
    {
        Http::fake(['https://api.mcube.com/*' => Http::response(['message' => 'accepted'], 200)]);

        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'token-123',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
        ]);

        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'email' => 'asm@example.test',
            'phone' => '09876543210',
        ]);
        $controller = app(\App\Http\Controllers\Api\SalesManagerDialerController::class);

        $request = Request::create('/api/sales-manager/dialer/call', 'POST', ['phone' => '+91 91234-56789']);
        $request->setUserResolver(fn () => $user);
        $response = $controller->call($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertDatabaseHas('mcube_outbound_attempts', [
            'user_id' => $user->id,
            'lead_id' => null,
            'task_id' => null,
            'agent_number' => '9876543210',
            'customer_number' => '9123456789',
            'status' => 'success',
        ]);
        $this->assertStringStartsWith('manual:user:' . $user->id . ':attempt:', McubeOutboundAttempt::first()->refid);

        Http::assertSent(fn ($request) =>
            $request['HTTP_AUTHORIZATION'] === 'token-123'
            && $request['exenumber'] === '9876543210'
            && $request['custnumber'] === '9123456789'
            && str_starts_with((string) $request['refid'], 'manual:user:' . $user->id . ':attempt:')
        );
    }

    public function test_sales_manager_manual_dialer_blocks_other_users(): void
    {
        $user = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), [
            'email' => 'other@example.test',
            'phone' => '9876543210',
        ]);
        $controller = app(\App\Http\Controllers\Api\SalesManagerDialerController::class);

        $request = Request::create('/api/sales-manager/dialer/call', 'POST', ['phone' => '9123456789']);
        $request->setUserResolver(fn () => $user);
        $response = $controller->call($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, McubeOutboundAttempt::count());
    }

    public function test_sales_manager_manual_dialer_allows_senior_manager(): void
    {
        Http::fake(['https://api.mcube.com/*' => Http::response(['message' => 'accepted'], 200)]);

        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'token-123',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
        ]);

        $user = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'email' => 'senior@example.test',
            'phone' => '9876543210',
        ]);
        $controller = app(\App\Http\Controllers\Api\SalesManagerDialerController::class);

        $request = Request::create('/api/sales-manager/dialer/call', 'POST', ['phone' => '9123456789']);
        $request->setUserResolver(fn () => $user);
        $response = $controller->call($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('mcube_outbound_attempts', [
            'user_id' => $user->id,
            'lead_id' => null,
            'customer_number' => '9123456789',
            'status' => 'success',
        ]);
    }

    public function test_sales_manager_manual_dialer_blocks_duplicate_number_attempts(): void
    {
        Http::fake(['https://api.mcube.com/*' => Http::response(['message' => 'accepted'], 200)]);

        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'token-123',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
        ]);

        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'email' => 'asm@example.test',
            'phone' => '9876543210',
        ]);
        $controller = app(\App\Http\Controllers\Api\SalesManagerDialerController::class);

        $firstRequest = Request::create('/api/sales-manager/dialer/call', 'POST', ['phone' => '9123456789']);
        $firstRequest->setUserResolver(fn () => $user);
        $this->assertSame(200, $controller->call($firstRequest)->getStatusCode());

        $secondRequest = Request::create('/api/sales-manager/dialer/call', 'POST', ['phone' => '9123456789']);
        $secondRequest->setUserResolver(fn () => $user);
        $response = $controller->call($secondRequest);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['fallback_to_tel']);
        $this->assertSame(1, McubeOutboundAttempt::count());
    }

    public function test_mcube_settings_preserve_saved_outbound_token_and_validate_auto_call_lists(): void
    {
        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'existing-token',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
        ]);

        $activeUser = $this->createUser($this->createRole(Role::TELECALLER), ['phone' => '9876543210']);
        $inactiveUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), [
            'phone' => '9123456789',
            'is_active' => false,
        ]);
        $controller = app(\App\Http\Controllers\Admin\McubeIntegrationController::class);

        $request = Request::create('/admin/integrations/mcube/settings', 'POST', [
            'token' => 'inbound-token',
            'is_enabled' => '1',
            'outbound_enabled' => '1',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_token' => '',
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
            'auto_call_on_assignment' => '1',
            'auto_call_cooldown_minutes' => 15,
            'auto_call_quiet_start' => '20:00',
            'auto_call_quiet_end' => '09:00',
            'auto_call_allowed_sources' => 'meta, website',
            'auto_call_allowed_user_ids' => (string) $activeUser->id,
        ]);

        $controller->updateSettings($request);

        $settings = McubeSetting::first();
        $this->assertSame('existing-token', $settings->outbound_token);
        $this->assertSame(['meta', 'website'], $settings->auto_call_allowed_sources);
        $this->assertSame([$activeUser->id], $settings->auto_call_allowed_user_ids);

        $badRequest = Request::create('/admin/integrations/mcube/settings', 'POST', [
            'outbound_enabled' => '1',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
            'auto_call_allowed_sources' => 'random-source',
            'auto_call_allowed_user_ids' => (string) $inactiveUser->id,
        ]);

        $this->expectException(ValidationException::class);
        $controller->updateSettings($badRequest);
    }

    public function test_calling_campaign_is_paused_after_repeated_mcube_failures(): void
    {
        McubeSetting::create(['outbound_enabled' => false]);

        $admin = $this->createUser($this->createRole(Role::ADMIN), ['phone' => '9999999999']);
        $agent = $this->createUser($this->createRole(Role::TELECALLER), ['phone' => '9876543210']);
        $campaign = CallingCenterCampaign::create([
            'name' => 'Failure Guard',
            'assigned_to' => $agent->id,
            'created_by' => $admin->id,
            'status' => CallingCenterCampaign::STATUS_RUNNING,
            'delay_seconds' => 1,
            'retry_policy' => 'none',
            'max_retries' => 0,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $lead = Lead::create(['name' => 'Fail Lead ' . $i, 'phone' => '91234567' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'source' => 'meta', 'status' => 'new']);
            CallingCenterCampaignItem::create([
                'campaign_id' => $campaign->id,
                'lead_id' => $lead->id,
                'phone' => $lead->phone,
                'status' => CallingCenterCampaignItem::STATUS_PENDING,
                'next_call_at' => now()->subSecond(),
            ]);
        }

        $service = app(CallingCenterService::class);
        for ($i = 1; $i <= 5; $i++) {
            $service->triggerNextForCampaign($campaign->fresh());
        }

        $this->assertSame(CallingCenterCampaign::STATUS_PAUSED, $campaign->fresh()->status);
        $this->assertSame(5, CallingCenterCampaignItem::where('campaign_id', $campaign->id)
            ->where('status', CallingCenterCampaignItem::STATUS_FAILED)
            ->count());
    }

    public function test_calling_campaign_creation_quantity_is_limited_by_role(): void
    {
        $role = $this->createRole(Role::CRM);
        $role->update(['permissions' => ['calling_center.create_campaign']]);
        $user = $this->createUser($role, ['phone' => '9876543210']);
        $controller = app(\App\Http\Controllers\CallingCenterController::class);
        $request = Request::create('/calling-center/preview', 'POST', ['quantity' => 501]);
        $request->setUserResolver(fn () => $user);

        $this->expectException(ValidationException::class);
        $controller->preview($request);
    }

    public function test_masked_user_receives_masked_phone_and_cannot_override_cloud_call_number(): void
    {
        $user = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['phone' => '9876543210']);
        $lead = Lead::create(['name' => 'Protected Buyer', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assignment_type' => 'primary',
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        UserPhonePrivacySetting::create([
            'user_id' => $user->id,
            'mask_enabled' => true,
            'call_mode' => UserPhonePrivacySetting::CALL_CLOUD_PREFERRED,
            'whatsapp_mode' => UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user);
        $this->assertSame('91XXXX6789', $lead->fresh()->phone);
        $this->assertArrayNotHasKey('normalized_phone', $lead->fresh()->toArray());

        $request = Request::create('/api/mcube/outbound-call', 'POST', [
            'lead_id' => $lead->id,
            'phone' => '9000000000',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(\App\Http\Controllers\Api\McubeOutboundCallController::class)->store($request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringNotContainsString('9000000000', $response->getContent());
        $this->assertDatabaseHas('phone_privacy_audits', [
            'actor_user_id' => $user->id,
            'lead_id' => $lead->id,
            'action' => 'blocked_raw_phone_override',
        ]);
    }

    public function test_masked_cloud_preferred_failure_requires_audited_explicit_fallback(): void
    {
        Http::fake(['https://api.mcube.com/*' => Http::response(['message' => 'provider unavailable'], 503)]);
        McubeSetting::create([
            'outbound_enabled' => true,
            'outbound_token' => 'token-123',
            'outbound_api_url' => McubeSetting::DEFAULT_OUTBOUND_API_URL,
            'outbound_auth_mode' => McubeSetting::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
        ]);

        $user = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['phone' => '9876543210']);
        $lead = Lead::create(['name' => 'Fallback Buyer', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assignment_type' => 'primary',
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        UserPhonePrivacySetting::create([
            'user_id' => $user->id,
            'mask_enabled' => true,
            'call_mode' => UserPhonePrivacySetting::CALL_CLOUD_PREFERRED,
            'whatsapp_mode' => UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED,
            'updated_by' => $user->id,
        ]);

        $controller = app(\App\Http\Controllers\Api\McubeOutboundCallController::class);
        $request = Request::create('/api/mcube/outbound-call', 'POST', ['lead_id' => $lead->id, 'phone_slot' => 'primary']);
        $request->setUserResolver(fn () => $user);
        $response = $controller->store($request);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertTrue($payload['fallback_available']);
        $this->assertArrayNotHasKey('dialer_phone', $payload);
        $this->assertStringNotContainsString('9123456789', $response->getContent());

        $attempt = McubeOutboundAttempt::findOrFail($payload['attempt_id']);
        $fallbackRequest = Request::create('/api/mcube/outbound-call/' . $attempt->id . '/fallback', 'POST');
        $fallbackRequest->setUserResolver(fn () => $user);
        $fallback = $controller->fallback($fallbackRequest, $attempt);

        $this->assertSame('+919123456789', $fallback->getData(true)['dialer_phone']);
        $this->assertDatabaseHas('phone_privacy_audits', [
            'actor_user_id' => $user->id,
            'lead_id' => $lead->id,
            'action' => 'dialer_fallback_revealed',
        ]);
    }

    public function test_cloud_only_blocks_fallback_and_whatsapp_api_only_blocks_direct_redirect(): void
    {
        $user = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['phone' => '9876543210']);
        $lead = Lead::create(['name' => 'Strict Buyer', 'phone' => '9123456789', 'source' => 'meta', 'status' => 'new']);
        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assignment_type' => 'primary',
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        UserPhonePrivacySetting::create([
            'user_id' => $user->id,
            'mask_enabled' => true,
            'call_mode' => UserPhonePrivacySetting::CALL_CLOUD_ONLY,
            'whatsapp_mode' => UserPhonePrivacySetting::WHATSAPP_API_ONLY,
            'updated_by' => $user->id,
        ]);
        $attempt = McubeOutboundAttempt::create([
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'agent_number' => $user->phone,
            'customer_number' => $lead->getRawOriginal('phone'),
            'status' => 'failed',
            'attempted_at' => now(),
        ]);

        $fallbackRequest = Request::create('/api/mcube/outbound-call/' . $attempt->id . '/fallback', 'POST');
        $fallbackRequest->setUserResolver(fn () => $user);
        $fallback = app(\App\Http\Controllers\Api\McubeOutboundCallController::class)
            ->fallback($fallbackRequest, $attempt);
        $this->assertSame(403, $fallback->getStatusCode());
        $this->assertStringNotContainsString('9123456789', $fallback->getContent());

        $whatsappRequest = Request::create('/api/leads/' . $lead->id . '/whatsapp-direct', 'POST');
        $whatsappRequest->setUserResolver(fn () => $user);
        $whatsapp = app(\App\Http\Controllers\Api\LeadCommunicationController::class)
            ->whatsappDirect($whatsappRequest, $lead, app(\App\Services\PhonePrivacyService::class));
        $this->assertSame(403, $whatsapp->getStatusCode());
        $this->assertStringNotContainsString('9123456789', $whatsapp->getContent());
        $this->assertDatabaseHas('phone_privacy_audits', ['action' => 'blocked_dialer_fallback']);
        $this->assertDatabaseHas('phone_privacy_audits', ['action' => 'blocked_direct_whatsapp']);
    }

    public function test_response_privacy_layer_removes_raw_phone_from_json_and_html(): void
    {
        $user = $this->createUser($this->createRole(Role::SALES_EXECUTIVE));
        UserPhonePrivacySetting::create([
            'user_id' => $user->id,
            'mask_enabled' => true,
            'call_mode' => UserPhonePrivacySetting::CALL_CLOUD_PREFERRED,
            'whatsapp_mode' => UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED,
            'updated_by' => $user->id,
        ]);
        $middleware = app(\App\Http\Middleware\MaskCustomerPhoneResponses::class);

        $jsonRequest = Request::create('/api/leads', 'GET');
        $jsonRequest->setUserResolver(fn () => $user);
        $json = $middleware->handle($jsonRequest, fn () => response()->json([
            'lead' => ['phone' => '9123456789'],
            'action_url' => 'tel:+919123456789',
        ]));
        $this->assertStringNotContainsString('9123456789', $json->getContent());
        $this->assertStringContainsString('91XXXX6789', $json->getContent());

        $htmlRequest = Request::create('/leads/1', 'GET');
        $htmlRequest->setUserResolver(fn () => $user);
        $html = $middleware->handle($htmlRequest, fn () => response('<div data-phone="9123456789">9123456789</div>'));
        $this->assertStringNotContainsString('9123456789', $html->getContent());
        $this->assertStringContainsString('91XXXX6789', $html->getContent());
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_phone_privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('mask_enabled')->default(false);
            $table->string('call_mode')->default(UserPhonePrivacySetting::CALL_CLOUD_PREFERRED);
            $table->string('whatsapp_mode')->default(UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('phone_privacy_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->boolean('is_dead')->default(false);
            $table->unsignedInteger('cnp_count')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->timestamp('whatsapp_opted_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->unsignedBigInteger('filled_by_user_id')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('import_kind')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('total_leads')->default(0);
            $table->unsignedInteger('imported_leads')->default(0);
            $table->unsignedInteger('failed_leads')->default(0);
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('imported_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->json('import_data')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_folder')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('lead_tag_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->default('phone_call');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->timestamp('outcome_recorded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamps();
        });

        Schema::create('mcube_settings', function (Blueprint $table) {
            $table->id();
            $table->string('token')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('outbound_enabled')->default(false);
            $table->string('outbound_api_url')->nullable();
            $table->string('outbound_token')->nullable();
            $table->string('outbound_auth_mode')->default(McubeSetting::OUTBOUND_AUTH_JSON);
            $table->string('default_refurl')->default('1');
            $table->boolean('auto_call_on_assignment')->default(false);
            $table->unsignedSmallInteger('auto_call_cooldown_minutes')->default(10);
            $table->time('auto_call_quiet_start')->nullable();
            $table->time('auto_call_quiet_end')->nullable();
            $table->json('auto_call_allowed_sources')->nullable();
            $table->json('auto_call_allowed_user_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('mcube_outbound_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('calling_center_campaign_item_id')->nullable();
            $table->string('agent_number', 30)->nullable();
            $table->string('customer_number', 30)->nullable();
            $table->string('refid')->nullable();
            $table->string('refurl')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('calling_center_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('created_by');
            $table->string('source_type')->default('manual');
            $table->string('folder_type')->nullable();
            $table->string('folder_key')->nullable();
            $table->unsignedBigInteger('folder_tag_id')->nullable();
            $table->json('filters')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('delay_seconds')->default(120);
            $table->string('retry_policy')->default('none');
            $table->unsignedTinyInteger('max_retries')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('calling_center_campaign_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('mcube_outbound_attempt_id')->nullable();
            $table->unsignedBigInteger('call_log_id')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status')->default('pending');
            $table->string('call_status')->nullable();
            $table->string('outcome')->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('next_call_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('call_started_at')->nullable();
            $table->timestamp('call_ended_at')->nullable();
            $table->timestamp('outcome_submitted_at')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'permissions' => [],
        ]);
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'User ' . $counter,
            'email' => 'mcubeuser' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function markAsLeadBankLead(Lead $lead, User $user): void
    {
        $batchId = DB::table('import_batches')->insertGetId([
            'user_id' => $user->id,
            'source_type' => 'csv',
            'import_kind' => 'lead_bank',
            'file_name' => 'lead-bank.csv',
            'total_leads' => 1,
            'imported_leads' => 1,
            'failed_leads' => 0,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('imported_leads')->insert([
            'import_batch_id' => $batchId,
            'lead_id' => $lead->id,
            'import_data' => json_encode(['action' => 'create']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
