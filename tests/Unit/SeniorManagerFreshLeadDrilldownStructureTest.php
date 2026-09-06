<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SeniorManagerFreshLeadDrilldownStructureTest extends TestCase
{
    public function test_fresh_lead_drilldown_is_scoped_and_wired_to_the_team_dashboard(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(
            fn ($route) => $route->uri() === 'api/sales-manager/dashboard/team-member/{member}/fresh-leads'
        );

        $this->assertNotNull($route);
        $this->assertContains('profile.heavy', $route->gatherMiddleware());

        $controller = file_get_contents(app_path('Http/Controllers/Api/SalesManagerController.php'));
        $this->assertStringContainsString('getDashboardTeamMemberFreshLeads', $controller);
        $this->assertStringContainsString('(int) $member->manager_id !== (int) $manager->id', $controller);
        $this->assertStringContainsString("whereIn('status', ['new', Lead::STATUS_FRESH_TRANSFER])", $controller);
        $this->assertStringContainsString("whereBetween('created_at', [\$range['start_date'], \$range['end_date']])", $controller);

        $view = file_get_contents(resource_path('views/sales-manager/dashboard.blade.php'));
        $this->assertStringContainsString('data-sm-fresh-toggle', $view);
        $this->assertStringContainsString('View fresh and new leads', $view);
        $this->assertStringContainsString('toggleSmTeamFreshDetails', $view);
        $this->assertStringContainsString('smTeamFreshLeadQuery', $view);
        $this->assertStringContainsString('lead.phone', $view);
    }
}
