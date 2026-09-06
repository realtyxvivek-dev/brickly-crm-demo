<?php

namespace Tests\Unit;

use Tests\TestCase;

class HrVerificationHistoryStructureTest extends TestCase
{
    public function test_hr_verified_history_is_scoped_to_the_current_verifier(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Hr/VerificationController.php'));

        $this->assertStringContainsString("where('verification_status', 'verified')", $controller);
        $this->assertSame(2, substr_count($controller, "where('verified_by', \$userId)"));
    }

    public function test_hr_verification_page_exposes_pending_and_verified_views(): void
    {
        $view = file_get_contents(resource_path('views/hr-manager/verifications.blade.php'));
        $routes = file_get_contents(base_path('routes/api.php'));

        $this->assertStringContainsString('hrPendingPanel', $view);
        $this->assertStringContainsString('hrVerifiedPanel', $view);
        $this->assertStringContainsString('/api/hr-manager/verifications/verified', $view);
        $this->assertStringContainsString("Route::get('/verifications/verified'", $routes);
    }
}
