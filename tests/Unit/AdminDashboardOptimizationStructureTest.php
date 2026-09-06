<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardOptimizationStructureTest extends TestCase
{
    public function test_dashboard_shell_does_not_build_lead_quality_report_synchronously(): void
    {
        $method = new ReflectionMethod(AdminDashboardController::class, 'dashboard');

        $this->assertSame(0, $method->getNumberOfParameters());
    }

    public function test_dashboard_exposes_lazy_lead_quality_route_and_v3_cache(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminDashboardController.php'));
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString('admin-dashboard:v3:', $controller);
        $this->assertStringContainsString('function loadLeadQualityOverview', $view);
        $this->assertStringContainsString("route('admin.dashboard.lead-quality')", $view);
    }

    public function test_dashboard_cache_reuses_data_until_refresh_is_requested(): void
    {
        config()->set('cache.default', 'array');
        Cache::flush();
        $controller = (new ReflectionClass(AdminDashboardController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, 'rememberDashboardData');
        $method->setAccessible(true);
        $builds = 0;
        $builder = function () use (&$builds): array {
            return ['build' => ++$builds];
        };

        $first = $method->invoke($controller, 'dashboard-test-key', 60, false, $builder);
        $warm = $method->invoke($controller, 'dashboard-test-key', 60, false, $builder);
        $refreshed = $method->invoke($controller, 'dashboard-test-key', 60, true, $builder);

        $this->assertSame(['build' => 1], $first);
        $this->assertSame($first, $warm);
        $this->assertSame(['build' => 2], $refreshed);
    }
}
