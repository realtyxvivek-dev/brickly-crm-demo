<?php

namespace Tests\Feature;

use App\Services\LeadAuditorDashboardService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadAuditorDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00', 'Asia/Kolkata'));
        Schema::create('roles', function (Blueprint $t) {$t->id(); $t->string('slug');});
        Schema::create('users', function (Blueprint $t) {$t->id(); $t->integer('role_id'); $t->string('name'); $t->boolean('is_active')->default(true); $t->softDeletes();});
        Schema::create('leads', function (Blueprint $t) {$t->id(); $t->string('name'); $t->string('status')->nullable(); $t->string('phone')->nullable(); $t->string('source')->nullable(); $t->integer('transferred_to_user_id')->nullable(); $t->timestamp('transferred_at')->nullable(); $t->softDeletes();});
        Schema::create('lead_assignments', function (Blueprint $t) {$t->id(); $t->integer('lead_id'); $t->integer('assigned_to'); $t->boolean('is_active')->default(true); $t->string('assignment_type')->default('primary'); $t->timestamp('assigned_at')->nullable();});
        Schema::table('lead_assignments', function (Blueprint $t) {$t->string('assignment_method')->nullable(); $t->text('notes')->nullable();});
        Schema::create('task_activities', function (Blueprint $t) {$t->id(); $t->integer('task_id'); $t->integer('user_id')->nullable(); $t->string('activity_type'); $t->string('new_value')->nullable(); $t->timestamps();});
        Schema::create('prospects', function (Blueprint $t) {$t->id(); $t->integer('lead_id'); $t->string('verification_status'); $t->timestamps();});
        foreach (['tasks', 'telecaller_tasks', 'follow_ups', 'meetings', 'site_visits'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->integer('lead_id'); $t->integer('assigned_to')->nullable(); $t->integer('created_by')->nullable();
                foreach (['meeting_id', 'site_visit_id', 'follow_up_id', 'reminder_task_id', 'pre_meeting_call_task_id'] as $key) $t->integer($key)->nullable();
                foreach (['type', 'task_type', 'outcome'] as $key) $t->string($key)->nullable();
                foreach (['title', 'description', 'notes'] as $key) $t->text($key)->nullable();
                $t->string('status')->default('pending'); $t->timestamp('scheduled_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamp('outcome_recorded_at')->nullable(); $t->timestamp('queue_hidden_at')->nullable(); $t->timestamps(); $t->softDeletes();
            });
        }
        DB::table('roles')->insert(['id' => 1, 'slug' => 'sales_manager']);
        DB::table('users')->insert([['id' => 1, 'name' => 'First Owner', 'role_id' => 1], ['id' => 2, 'name' => 'Second Owner', 'role_id' => 1]]);
        Schema::table('leads', function (Blueprint $t) {$t->integer('cnp_count')->default(0); $t->timestamp('next_followup_at')->nullable();});
        DB::table('leads')->insert(['id' => 1, 'name' => 'Customer', 'status' => 'new']);
        DB::table('lead_assignments')->insert(['lead_id' => 1, 'assigned_to' => 1, 'assigned_at' => '2026-09-04 08:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(array $values = [], string $table = 'tasks'): int
    {
        return DB::table($table)->insertGetId(array_merge(['lead_id' => 1, 'assigned_to' => 1, 'type' => 'phone_call', 'task_type' => 'calling', 'created_at' => '2026-09-04 08:00:00', 'scheduled_at' => '2026-09-04 10:00:00', 'status' => 'pending'], $values));
    }

    public function test_untouched_requires_valid_completion_and_accepts_automatic_cnp(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        $id = $this->task(['status' => 'in_progress']);
        $this->assertCount(1, $service->untouched()->get());
        DB::table('tasks')->where('id', $id)->update(['status' => 'completed', 'completed_at' => '2026-09-04 11:00:00']);
        $this->assertCount(1, $service->untouched()->get(), 'Cleanup-only completion must not touch the lead');
        DB::table('tasks')->where('id', $id)->update(['outcome' => 'cnp', 'outcome_recorded_at' => '2026-09-04 11:00:00']);
        $this->assertCount(0, $service->untouched()->get());
    }

    public function test_transfer_ignores_old_owner_and_previous_assignment_completion(): void
    {
        $this->task(['status' => 'completed', 'outcome' => 'connected', 'completed_at' => '2026-09-04 09:00:00']);
        DB::table('lead_assignments')->insert(['lead_id' => 1, 'assigned_to' => 2, 'assigned_at' => '2026-09-04 10:00:00']);
        DB::table('leads')->where('id', 1)->update(['transferred_to_user_id' => 2, 'transferred_at' => '2026-09-04 10:00:00']);
        $service = app(LeadAuditorDashboardService::class);
        $rows = $service->untouched()->get();
        $this->assertCount(1, $rows);
        $this->assertSame('fresh_transfer', $rows[0]->assignment_type);
        $this->assertEquals(2, $rows[0]->user_id);
        $this->task(['assigned_to' => 2, 'created_at' => '2026-09-03 08:00:00', 'status' => 'completed', 'outcome' => 'connected', 'completed_at' => '2026-09-04 11:00:00']);
        $this->assertCount(1, $service->untouched()->get());
        $this->task(['assigned_to' => 2, 'created_at' => '2026-09-04 10:00:00', 'status' => 'completed', 'outcome' => 'not_interested', 'completed_at' => '2026-09-04 11:00:00'], 'telecaller_tasks');
        $this->assertCount(0, $service->untouched()->get());
    }

    public function test_summary_drilldown_reconciles_and_deduplicates_reminders(): void
    {
        $this->task();
        $this->task(['scheduled_at' => '2026-09-03 10:00:00', 'status' => 'completed', 'outcome' => 'cnp', 'completed_at' => '2026-09-04 11:00:00']);
        $this->task(['status' => 'cancelled']);
        $this->task(['status' => 'completed', 'completed_at' => '2026-09-04 11:00:00']);
        $meeting = DB::table('meetings')->insertGetId(['lead_id' => 1, 'assigned_to' => 1, 'status' => 'completed', 'scheduled_at' => '2026-09-04 10:00:00', 'completed_at' => '2026-09-04 11:00:00']);
        $this->task(['meeting_id' => $meeting]);
        $service = app(LeadAuditorDashboardService::class);
        $rows = collect($service->summary()['users']);
        $first = $rows->firstWhere('id', 1);
        $this->assertSame(2, $first['due']);
        $this->assertSame(2, $first['completed_today']);
        $this->assertSame(1, $first['remaining']);
        $this->assertSame(0, $rows->firstWhere('id', 2)['due']);
        foreach (['due', 'completed_today', 'remaining'] as $bucket) $this->assertCount($first[$bucket], $service->drilldown(1, $bucket, null)->get());
        $this->assertCount(1, $service->drilldown(1, 'done', 'meeting')->get());
    }

    public function test_follow_up_phone_calls_are_not_reported_as_initial_lead_tasks(): void
    {
        $this->task(['title' => 'Call lead: Customer']);
        $this->task(['title' => 'Follow-up call: Customer']);
        $this->task(['title' => 'CNP retry follow up call: Customer', 'notes' => 'ASM follow up CNP automation retry task']);

        $row = collect(app(LeadAuditorDashboardService::class)->summary()['users'])->firstWhere('id', 1);

        $this->assertSame(['due' => 1, 'done' => 0], $row['categories']['initial']);
        $this->assertSame(['due' => 2, 'done' => 0], $row['categories']['follow_up']);
        $this->assertCount(1, app(LeadAuditorDashboardService::class)->drilldown(1, 'due', 'initial')->get());
        $this->assertCount(2, app(LeadAuditorDashboardService::class)->drilldown(1, 'due', 'follow_up')->get());
    }

    public function test_legacy_follow_up_record_and_calling_task_count_once_for_every_user(): void
    {
        DB::table('follow_ups')->insert([
            'lead_id' => 1,
            'created_by' => 1,
            'scheduled_at' => '2026-09-04 10:00:00',
            'status' => 'scheduled',
        ]);
        $this->task([
            'title' => 'Follow-up call: Customer',
            'status' => 'completed',
            'outcome' => 'follow_up',
            'completed_at' => '2026-09-04 11:00:00',
        ]);
        $this->task([
            'title' => 'Follow-up call: Customer',
            'scheduled_at' => '2026-09-04 16:00:00',
        ]);

        $service = app(LeadAuditorDashboardService::class);
        $row = collect($service->summary()['users'])->firstWhere('id', 1);

        $this->assertSame(['due' => 2, 'done' => 1], $row['categories']['follow_up']);
        $this->assertCount(2, $service->drilldown(1, 'due', 'follow_up')->get());
        $this->assertCount(1, $service->drilldown(1, 'done', 'follow_up')->get());
    }

    public function test_unknown_time_deleted_leads_and_exclusive_day_end(): void
    {
        DB::table('lead_assignments')->update(['assigned_at' => null]);
        $service = app(LeadAuditorDashboardService::class);
        $this->assertNull($service->untouched()->first()->assigned_at);
        $this->task(['scheduled_at' => '2026-09-05 00:00:00']);
        $this->assertSame(0, $service->summary()['users'][0]['due']);
        DB::table('leads')->where('id', 1)->update(['deleted_at' => now()]);
        $this->assertCount(0, $service->untouched()->get());
    }

    public function test_recorded_actor_and_generic_sales_completion(): void
    {
        $id = $this->task(['status' => 'completed', 'outcome' => 'connected', 'completed_at' => '2026-09-04 11:00:00']);
        DB::table('task_activities')->insert(['task_id' => $id, 'user_id' => 2, 'activity_type' => 'status_changed', 'new_value' => 'completed', 'created_at' => '2026-09-04 11:00:00']);
        $service = app(LeadAuditorDashboardService::class);
        $this->assertCount(1, $service->untouched()->get());
        $generic = $this->task(['type' => 'document_collection', 'status' => 'completed', 'completed_at' => '2026-09-04 11:00:00']);
        $this->assertCount(0, $service->drilldown(1, 'done', 'other')->get());
        DB::table('task_activities')->insert(['task_id' => $generic, 'user_id' => 1, 'activity_type' => 'status_changed', 'new_value' => 'completed', 'created_at' => '2026-09-04 11:00:00']);
        $this->assertCount(1, $service->drilldown(1, 'done', 'other')->get());
    }

    public function test_repeated_same_owner_assignment_and_rescheduled_linked_items(): void
    {
        $this->task(['status' => 'completed', 'outcome' => 'connected', 'completed_at' => '2026-09-04 09:00:00']);
        DB::table('lead_assignments')->insert(['lead_id' => 1, 'assigned_to' => 1, 'assigned_at' => '2026-09-04 10:00:00']);
        DB::table('lead_assignments')->insert(['lead_id' => 1, 'assigned_to' => 1, 'assigned_at' => '2026-09-04 10:00:00']);
        $service = app(LeadAuditorDashboardService::class);
        $this->assertCount(1, $service->untouched(1)->get());
        $this->assertCount(0, $service->untouched(2)->get());
        $followup = DB::table('follow_ups')->insertGetId(['lead_id' => 1, 'created_by' => 1, 'status' => 'pending', 'scheduled_at' => '2026-09-05 10:00:00']);
        $this->task(['follow_up_id' => $followup]);
        $this->task(['follow_up_id' => $followup], 'telecaller_tasks');
        $this->assertCount(0, $service->drilldown(1, 'due', 'follow_up')->get());
        DB::table('follow_ups')->where('id', $followup)->update(['scheduled_at' => '2026-09-04 13:00:00']);
        $this->assertCount(1, $service->drilldown(1, 'due', 'follow_up')->get());
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->assertCount(0, $service->untouched()->get());
        $this->assertCount(1, $service->summary()['users']);
    }

    public function test_later_outcome_edit_does_not_move_actual_completion_date(): void
    {
        $this->task(['status' => 'completed', 'outcome' => 'interested', 'completed_at' => '2026-09-03 11:00:00', 'outcome_recorded_at' => '2026-09-04 11:00:00']);
        $service = app(LeadAuditorDashboardService::class);
        $this->assertCount(0, $service->drilldown(1, 'completed_today', null)->get());
        $this->assertCount(1, $service->drilldown(1, 'done', null)->get());
    }

    public function test_terminal_leads_with_active_legacy_assignments_are_not_untouched(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        $this->task(['status' => 'cancelled']);
        foreach (['not_interested', 'junk', 'dead', 'closed'] as $status) {
            DB::table('leads')->where('id', 1)->update(['status' => $status]);
            $this->assertCount(0, $service->untouched()->get());
            $this->assertSame(0, $service->summary()['untouched']['total']);
        }
        DB::table('leads')->where('id', 1)->update(['status' => 'fresh_transfer', 'transferred_to_user_id' => 1, 'transferred_at' => '2026-09-04 09:00:00']);
        $this->assertCount(1, $service->untouched()->get());
        DB::table('leads')->where('id', 1)->update(['status' => 'connected']);
        $this->assertCount(0, $service->untouched()->get(), 'Only New and Fresh Transfer stages are eligible');
    }

    public function test_not_interested_completions_remain_in_today_work_history(): void
    {
        DB::table('leads')->where('id', 1)->update(['status' => 'not_interested']);
        $this->task(['status' => 'completed', 'outcome' => 'not_interested', 'completed_at' => '2026-09-04 11:00:00']);
        $service = app(LeadAuditorDashboardService::class);
        $this->assertCount(0, $service->untouched()->get());
        $this->assertCount(1, $service->drilldown(1, 'completed_today', null)->get());
        $this->assertCount(0, $service->drilldown(1, 'remaining', null)->get());
    }

    public function test_completed_activity_requires_current_owner_and_assignment_window(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        foreach (['meetings', 'site_visits'] as $table) {
            $id = DB::table($table)->insertGetId(['lead_id' => 1, 'assigned_to' => 2, 'status' => 'completed', 'scheduled_at' => '2026-09-04 09:00:00', 'completed_at' => '2026-09-04 10:00:00']);
            $this->assertCount(1, $service->untouched()->get());
            DB::table($table)->where('id', $id)->update(['assigned_to' => 1]);
            $this->assertCount(0, $service->untouched()->get());
            DB::table($table)->where('id', $id)->update(['scheduled_at' => '2026-09-03 09:00:00']);
            $this->assertCount(1, $service->untouched()->get());
            DB::table($table)->where('id', $id)->update(['scheduled_at' => '2026-09-04 09:00:00', 'status' => 'cancelled']);
            $this->assertCount(1, $service->untouched()->get());
        }
    }

    public function test_current_assignment_selects_latest_active_primary_not_latest_inactive(): void
    {
        DB::table('lead_assignments')->insert(['lead_id' => 1, 'assigned_to' => 2, 'assigned_at' => '2026-09-04 10:00:00', 'is_active' => false]);
        $lead = new \App\Models\Lead;
        $lead->setRawAttributes(['id' => 1]);
        $this->assertEquals(1, $lead->currentAssignment()->first()->assigned_to);
        $this->assertEquals(2, $lead->latestAssignment()->first()->assigned_to);
        DB::table('lead_assignments')->insert(['lead_id' => 1, 'assigned_to' => 2, 'assigned_at' => '2026-09-04 11:00:00', 'assignment_type' => 'secondary']);
        $this->assertEquals(1, $lead->currentAssignment()->first()->assigned_to);
    }

    public function test_legacy_blank_outcome_requires_owner_scheduling_and_completed_initial_task(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        foreach (['site_visits', 'meetings'] as $table) {
            $activity = DB::table($table)->insertGetId(['lead_id' => 1, 'assigned_to' => 1, 'created_by' => 1, 'status' => 'scheduled', 'created_at' => '2026-09-04 09:00:00', 'scheduled_at' => '2026-09-05 10:00:00']);
            $this->assertCount(1, $service->untouched()->get());
            $task = $this->task(['status' => 'completed', 'completed_at' => '2026-09-04 09:00:01']);
            $this->assertCount(0, $service->untouched()->get());
            $this->assertSame(0, $service->summary()['untouched']['total']);
            DB::table($table)->where('id', $activity)->update(['created_by' => 2]);
            $this->assertCount(1, $service->untouched()->get());
            DB::table($table)->where('id', $activity)->update(['created_by' => 1, 'created_at' => '2026-09-03 09:00:00']);
            $this->assertCount(1, $service->untouched()->get());
            DB::table($table)->where('id', $activity)->update(['created_at' => '2026-09-04 09:00:00', 'status' => 'cancelled']);
            $this->assertCount(1, $service->untouched()->get());
            DB::table('tasks')->where('id', $task)->delete();
        }
    }

    public function test_reused_task_with_recorded_current_owner_response_is_touched(): void
    {
        $id = $this->task(['created_at' => '2026-09-01 10:00:00', 'status' => 'completed', 'outcome' => 'schedule_visit', 'completed_at' => '2026-09-04 09:00:00']);
        $service = app(LeadAuditorDashboardService::class);
        $this->assertCount(1, $service->untouched()->get());
        DB::table('task_activities')->insert(['task_id' => $id, 'user_id' => 1, 'activity_type' => 'status_changed', 'new_value' => 'completed', 'created_at' => '2026-09-04 09:00:00']);
        $this->assertCount(0, $service->untouched()->get());
        DB::table('task_activities')->update(['created_at' => '2026-09-01 11:00:00']);
        $this->assertCount(1, $service->untouched()->get());
    }

    public function test_untouched_date_ranges_use_assignment_date_and_match_header(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        foreach (['all', 'today', 'week', 'month', 'year'] as $range) {
            $this->assertCount(1, $service->untouched(null, $range)->get());
        }
        DB::table('lead_assignments')->update(['assigned_at' => '2026-08-31 10:00:00']);
        $this->assertCount(0, $service->untouched(null, 'today')->get());
        $this->assertCount(0, $service->untouched(null, 'month')->get());
        $this->assertCount(1, $service->untouched(null, 'week')->get());
        $this->assertCount(1, $service->untouched(null, 'year')->get());
        foreach (['all', 'today', 'week', 'month', 'year'] as $range) {
            $this->assertSame($service->untouched(null, $range)->count(), $service->summary(null, $range)['untouched']['total']);
        }
        DB::table('lead_assignments')->update(['assigned_at' => null]);
        $this->assertCount(0, $service->untouched(null, 'year')->get());
        $this->assertCount(1, $service->untouched()->get());
    }

    public function test_transfer_marker_does_not_override_cnp_or_follow_up_stage(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        foreach (['new', 'fresh_transfer'] as $status) {
            DB::table('leads')->update(['status' => $status, 'cnp_count' => 0, 'next_followup_at' => null]);
            $this->assertCount(1, $service->untouched()->get());
            foreach ([['cnp_count' => 1, 'next_followup_at' => null], ['cnp_count' => 0, 'next_followup_at' => '2026-09-05 09:00:00']] as $stage) {
                DB::table('leads')->update($stage);
                foreach (['all', 'today', 'week', 'month', 'year'] as $range) {
                    $this->assertCount(0, $service->untouched(null, $range)->get());
                    $this->assertSame(0, $service->summary(null, $range)['untouched']['total']);
                }
            }
        }
    }

    public function test_only_new_and_fresh_transfer_without_current_cnp_are_eligible(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        foreach (['cnp','connected','follow_up','visit_scheduled','visit_done','meeting_scheduled',null] as $status) {
            DB::table('leads')->update(['status' => $status]);
            $this->assertCount(0, $service->untouched()->get());
        }
        DB::table('leads')->update(['status' => 'new']);
        $id = $this->task(['status' => 'cancelled', 'outcome' => 'cnp', 'outcome_recorded_at' => '2026-09-04 09:00:00']);
        $this->assertCount(0, $service->untouched()->get());
        $this->assertSame(0, $service->summary()['untouched']['total']);
        DB::table('tasks')->where('id', $id)->update(['created_at' => '2026-09-03 08:00:00', 'outcome_recorded_at' => '2026-09-03 09:00:00']);
        DB::table('leads')->update(['status' => 'fresh_transfer', 'transferred_to_user_id' => 1, 'transferred_at' => '2026-09-04 08:00:00']);
        $this->assertCount(0, $service->untouched()->get(), 'Retained CNP history still resolves to CNP in All Leads');
        DB::table('tasks')->where('id', $id)->update(['deleted_at' => now()]);
        $this->assertCount(1, $service->untouched()->get());
    }

    public function test_retained_crm_stage_history_is_not_new_even_without_completion_timestamp(): void
    {
        $service = app(LeadAuditorDashboardService::class);
        foreach (['new', 'fresh_transfer'] as $status) {
            DB::table('leads')->update(['status' => $status]);
            foreach (['cnp', 'not_interested', 'junk', 'interested', 'follow_up', 'visit_done', 'meeting_done'] as $outcome) {
                $id = $this->task(['assigned_to' => 2, 'outcome' => $outcome, 'status' => 'cancelled', 'created_at' => '2026-09-01 08:00:00']);
                $this->assertCount(0, $service->untouched()->get());
                $this->assertSame(0, $service->summary()['untouched']['total']);
                DB::table('tasks')->where('id', $id)->delete();
            }
            $id = $this->task(['title' => 'CNP retry task created']);
            $this->assertCount(0, $service->untouched()->get());
            DB::table('tasks')->where('id', $id)->delete();
            DB::table('prospects')->insert(['lead_id' => 1, 'verification_status' => 'verified', 'updated_at' => now()]);
            $this->assertCount(0, $service->untouched()->get());
            DB::table('prospects')->delete();
            $this->assertCount(1, $service->untouched()->get());
        }
    }
}
