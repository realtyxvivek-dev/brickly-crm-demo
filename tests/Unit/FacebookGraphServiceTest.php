<?php

namespace Tests\Unit;

use App\Services\FacebookGraphService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookGraphServiceTest extends TestCase
{
    public function test_get_form_leads_reads_paginated_meta_response_until_limit(): void
    {
        Http::fake([
            'graph.facebook.com/*/form-1/leads*' => Http::sequence()
                ->push([
                    'data' => [
                        ['id' => 'lead-1', 'created_time' => '2026-08-10T08:00:00+0000'],
                    ],
                    'paging' => ['next' => 'https://graph.facebook.com/v18.0/form-1/leads?page=2'],
                ])
                ->push([
                    'data' => [
                        ['id' => 'lead-2', 'created_time' => '2026-08-10T09:00:00+0000'],
                    ],
                ]),
        ]);

        $result = FacebookGraphService::fromToken('page-token', 'v18.0')
            ->getFormLeads('form-1', '2026-08-03T00:00:00+00:00', '2026-08-10T23:59:59+00:00', 2);

        $this->assertTrue($result['success']);
        $this->assertSame(['lead-1', 'lead-2'], collect($result['leads'])->pluck('id')->all());
    }
}
