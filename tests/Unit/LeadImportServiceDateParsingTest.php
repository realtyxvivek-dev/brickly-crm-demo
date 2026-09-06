<?php

namespace Tests\Unit;

use App\Services\LeadImportService;
use Mockery;
use Tests\TestCase;

class LeadImportServiceDateParsingTest extends TestCase
{
    public function test_it_parses_ambiguous_numeric_dates_as_day_month_year(): void
    {
        $service = new LeadImportService(
            Mockery::mock(\App\Services\LeadAssignmentService::class),
            Mockery::mock(\App\Services\TaskService::class),
            Mockery::mock(\App\Services\NotificationService::class),
        );

        $method = new \ReflectionMethod(LeadImportService::class, 'parseImportedDateValue');
        $method->setAccessible(true);

        $parsed = $method->invoke($service, '04/05/2026 09:30 AM');

        $this->assertNotNull($parsed);
        $this->assertSame('2026-05-04 09:30', $parsed->format('Y-m-d H:i'));
    }

    public function test_it_parses_short_numeric_dates_as_day_month_year(): void
    {
        $service = new LeadImportService(
            Mockery::mock(\App\Services\LeadAssignmentService::class),
            Mockery::mock(\App\Services\TaskService::class),
            Mockery::mock(\App\Services\NotificationService::class),
        );

        $method = new \ReflectionMethod(LeadImportService::class, 'parseImportedDateValue');
        $method->setAccessible(true);

        $parsed = $method->invoke($service, '04-05-26');

        $this->assertNotNull($parsed);
        $this->assertSame('2026-05-04 00:00', $parsed->format('Y-m-d H:i'));
    }

    public function test_simple_import_mapper_applies_review_mappings_and_row_overrides(): void
    {
        $service = new LeadImportService(
            Mockery::mock(\App\Services\LeadAssignmentService::class),
            Mockery::mock(\App\Services\TaskService::class),
            Mockery::mock(\App\Services\NotificationService::class),
        );

        $method = new \ReflectionMethod(LeadImportService::class, 'mapSimpleImportRow');
        $method->setAccessible(true);

        $mapped = $method->invoke(
            $service,
            [
                '_row_number' => 2,
                'values' => ['Original Name', '99999', 'Hot Prospect', 'NAVEEN', 'Old Source'],
            ],
            [
                ['index' => 0, 'label' => 'Name'],
                ['index' => 1, 'label' => 'Phone'],
                ['index' => 2, 'label' => 'Lead Stage'],
                ['index' => 3, 'label' => 'Owner'],
                ['index' => 4, 'label' => 'Lead Source'],
            ],
            [
                0 => 'name',
                1 => 'phone',
                2 => 'lead_stage',
                3 => 'owner',
                4 => 'source',
            ],
            ['naveen' => null],
            ['hot prospect' => 'meeting'],
            [
                2 => [
                    'phone' => '919999900000',
                    'source' => 'Reference',
                ],
            ]
        );

        $this->assertSame('919999900000', $mapped['lead']['phone']);
        $this->assertSame('reference', $mapped['lead']['source']);
        $this->assertSame('meeting', $mapped['pipeline_stage']);
        $this->assertSame('meeting_scheduled', $mapped['lead']['status']);
        $this->assertNull($mapped['assigned_user_id']);
        $this->assertSame([], $mapped['errors']);
    }
}
