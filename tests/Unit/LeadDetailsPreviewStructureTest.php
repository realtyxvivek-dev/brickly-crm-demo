<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LeadDetailsPreviewStructureTest extends TestCase
{
    public function test_preview_route_is_admin_only_and_classic_route_remains_separate(): void
    {
        $preview = Route::getRoutes()->getByName('leads.preview');
        $classic = Route::getRoutes()->getByName('leads.show');

        $this->assertNotNull($preview);
        $this->assertSame('leads/{lead}/preview', $preview->uri());
        $this->assertContains('role:admin', $preview->gatherMiddleware());
        $this->assertSame('leads/{lead}', $classic->uri());

        $controller = file_get_contents(app_path('Http/Controllers/LeadController.php'));
        $this->assertStringContainsString("abort_unless(\$request->user()?->isAdmin(), 403);", $controller);
        $this->assertStringContainsString("lead_details_preview", $controller);
    }

    public function test_classic_and_preview_markup_are_isolated(): void
    {
        $classic = file_get_contents(resource_path('views/leads/show.blade.php'));
        $preview = file_get_contents(resource_path('views/leads/partials/preview-v2.blade.php'));

        $this->assertStringContainsString("@if(\$leadDetailsPreview)", $classic);
        $this->assertStringContainsString("@include('leads.partials.preview-v2')", $classic);
        $this->assertStringContainsString('Try New View', $classic);
        $this->assertStringContainsString('Back to Classic', $preview);
        $this->assertStringContainsString('data-lead-v2', $preview);

        foreach (['overview', 'activities', 'requirements', 'proposals', 'documents', 'audit'] as $tab) {
            $this->assertStringContainsString("data-lead-v2-panel=\"{$tab}\"", $preview);
        }
    }

    public function test_preview_reuses_existing_actions_and_has_mobile_navigation(): void
    {
        $preview = file_get_contents(resource_path('views/leads/partials/preview-v2.blade.php'));

        foreach (['openFollowupModal()', 'openScheduleCallTaskModal()', 'openMeetingModal()', 'openSiteVisitModal()', 'openOwnerTransferModal()', 'markLeadAsCloserDraft()', 'openLeadRequirementsModal'] as $action) {
            $this->assertStringContainsString($action, $preview);
        }

        $this->assertStringContainsString("@include('leads.partials.reopen')", $preview);
        $this->assertStringContainsString("@include('leads.partials.proposal-card')", $preview);
        $this->assertStringContainsString('.lead-v2-mobile-actions{position:fixed', $preview);
        $this->assertStringContainsString('window.location.hash', $preview);
        $this->assertStringContainsString('lead-v2-remark-dialog', $preview);
        $this->assertStringContainsString('previewReadableActivity', $preview);
        $this->assertStringContainsString('white-space:nowrap', $preview);
        $this->assertStringContainsString('.lead-v2-audit-timeline{max-width:none}', $preview);
        $this->assertStringContainsString('View Visit Form', $preview);
        $this->assertStringContainsString('data-form-files', $preview);

        $activityService = file_get_contents(app_path('Services/LeadActivityService.php'));
        $this->assertStringContainsString("'tentative_closing_time' => 'Tentative Closing Time'", $activityService);
        $this->assertStringContainsString("'completion_proof_photos' => 'Completion Proof'", $activityService);

        $classic = file_get_contents(resource_path('views/leads/show.blade.php'));
        $this->assertStringContainsString("button.dataset.formFiles || 'W10='", $classic);
        $this->assertStringContainsString('Submitted files', $classic);
    }
}
