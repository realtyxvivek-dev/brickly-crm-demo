<?php

namespace Tests\Feature;

use App\Services\LeadQualityReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LeadQualityLatestOutcomeOptimizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        foreach (['telecaller_tasks', 'tasks'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
                $table->id();
                $table->unsignedBigInteger('lead_id');
                $table->string('outcome')->nullable();
                if ($tableName === 'tasks') {
                    $table->text('outcome_remark')->nullable();
                    $table->timestamp('outcome_recorded_at')->nullable();
                }
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function test_latest_outcome_is_selected_across_both_task_tables_in_one_query(): void
    {
        DB::table('telecaller_tasks')->insert([
            'lead_id' => 10,
            'outcome' => 'cnp',
            'notes' => 'Older call',
            'completed_at' => '2026-08-20 10:00:00',
            'created_at' => '2026-08-20 09:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ]);
        DB::table('tasks')->insert([
            'lead_id' => 10,
            'outcome' => 'interested',
            'outcome_remark' => 'Latest manager outcome',
            'notes' => null,
            'outcome_recorded_at' => '2026-08-20 11:00:00',
            'completed_at' => '2026-08-20 11:00:00',
            'created_at' => '2026-08-20 10:30:00',
            'updated_at' => '2026-08-20 11:00:00',
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $method = new ReflectionMethod(LeadQualityReportService::class, 'latestOutcomeTasks');
        $method->setAccessible(true);
        $rows = $method->invoke(new LeadQualityReportService(), new Collection([10]));

        $this->assertSame('interested', $rows[10]->outcome);
        $this->assertSame('Latest manager outcome', $rows[10]->outcome_remark);
        $this->assertSame(1, $queries);
    }
}
