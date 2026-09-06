<?php

namespace App\Http\Controllers\AdManager;

use App\Http\Controllers\Admin\FacebookLeadAdsController;
use App\Http\Controllers\Admin\MetaWabaIntegrationController;
use App\Http\Controllers\Admin\WhatsAppDebugController;
use App\Http\Controllers\Admin\WhatsAppIntegrationController;
use App\Http\Controllers\Admin\WhatsAppTestController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FacebookPortfolio;
use App\Models\FbForm;
use App\Models\FbPage;
use App\Models\MetaWabaAccount;
use App\Models\MetaBulkRecoveryScan;
use App\Models\WhatsAppTemplate;
use App\Services\MetaWabaApiService;
use App\Services\WabaCampaignService;
use Illuminate\Http\Request;

class MetaOpsController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->canManageMetaOps(), 403);

        return view('ad-manager.meta.index');
    }

    public function facebookLeadAdsIndex(FacebookLeadAdsController $controller)
    {
        return $controller->index();
    }

    public function facebookLeadAdsSettings(FacebookLeadAdsController $controller)
    {
        return $controller->settings();
    }

    public function updateFacebookLeadAdsSettings(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_settings_updated', $request);

        return $controller->updateSettings($request);
    }

    public function testFacebookLeadAdsConnection(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_connection_tested', $request);

        return $controller->testConnection($request);
    }

    public function testFacebookMarketingConnection(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_marketing_connection_tested', $request);

        return $controller->testMarketingConnection($request);
    }

    public function facebookLeadAdsForms(Request $request, FacebookLeadAdsController $controller)
    {
        return $controller->forms($request);
    }

    public function facebookLeadAdsDiagnostics(Request $request, FacebookLeadAdsController $controller)
    {
        return $controller->diagnostics($request);
    }

    public function facebookLeadAdsMissingChecker(FacebookLeadAdsController $controller)
    {
        return $controller->missingChecker();
    }

    public function previewMissingChecker(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_missing_checker_previewed', $request);

        return $controller->previewMissingChecker($request);
    }

    public function importMissingLeads(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_missing_leads_imported', $request);

        return $controller->importMissingLeads($request);
    }

    public function bulkRecoveryIndex(FacebookLeadAdsController $controller)
    {
        return $controller->bulkRecoveryIndex();
    }

    public function bulkRecoveryStoreScan(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_bulk_recovery_scan_started', $request);

        return $controller->bulkRecoveryStoreScan($request);
    }

    public function bulkRecoveryShow(MetaBulkRecoveryScan $scan, FacebookLeadAdsController $controller)
    {
        return $controller->bulkRecoveryShow($scan);
    }

    public function bulkRecoveryImport(Request $request, MetaBulkRecoveryScan $scan, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_bulk_recovery_import_started', $request, ['scan_id' => $scan->id]);

        return $controller->bulkRecoveryImport($request, $scan);
    }

    public function retryFailedWebhooks(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_failed_webhooks_retried', $request);

        return $controller->retryFailedWebhooks($request);
    }

    public function storePortfolio(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_portfolio_created', $request);

        return $controller->storePortfolio($request);
    }

    public function updatePortfolio(Request $request, FacebookPortfolio $portfolio, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_portfolio_updated', $request, ['portfolio_id' => $portfolio->id]);

        return $controller->updatePortfolio($request, $portfolio);
    }

    public function syncForms(Request $request, FacebookLeadAdsController $controller, ?FbPage $page = null)
    {
        $this->audit('ad_manager_meta_forms_synced', $request, ['page_id' => $page?->id]);

        return $controller->syncForms($request, $page);
    }

    public function pageDetail(FbPage $page, FacebookLeadAdsController $controller)
    {
        return $controller->pageDetail($page);
    }

    public function callbackPageLeads(Request $request, FbPage $page, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_page_callback_triggered', $request, ['page_id' => $page->id]);

        return $controller->callbackPageLeads($request, $page);
    }

    public function assignPagePortfolio(Request $request, FbPage $page, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_page_portfolio_assigned', $request, ['page_id' => $page->id]);

        return $controller->assignPagePortfolio($request, $page);
    }

    public function mapping(Request $request, string $formId, FacebookLeadAdsController $controller)
    {
        return $controller->mapping($request, $formId);
    }

    public function saveMapping(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_mapping_saved', $request, ['form_id' => $request->input('form_id')]);

        return $controller->saveMapping($request);
    }

    public function formDetail(FbForm $form, FacebookLeadAdsController $controller)
    {
        return $controller->formDetail($form);
    }

    public function callbackFormLeads(Request $request, FbForm $form, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_form_callback_triggered', $request, ['form_id' => $form->id]);

        return $controller->callbackFormLeads($request, $form);
    }

    public function toggleForm(FbForm $form, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_form_toggled', request(), ['form_id' => $form->id]);

        return $controller->toggleForm($form);
    }

    public function assignFormAutomation(Request $request, FbForm $form, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_form_automation_assigned', $request, ['form_id' => $form->id]);

        return $controller->assignAutomation($request, $form);
    }

    public function setFormState(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_form_state_updated', $request, ['form_id' => $request->input('form_id')]);

        return $controller->setFormState($request);
    }

    public function storeCustomField(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_custom_field_created', $request);

        return $controller->storeCustomField($request);
    }

    public function addPage(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_page_added', $request);

        return $controller->addPage($request);
    }

    public function removePage(Request $request, FacebookLeadAdsController $controller)
    {
        $this->audit('ad_manager_meta_page_removed', $request);

        return $controller->removePage($request);
    }

    public function whatsapp(WhatsAppIntegrationController $controller)
    {
        return $controller->index();
    }

    public function updateWhatsapp(Request $request, WhatsAppIntegrationController $controller)
    {
        $this->audit('ad_manager_whatsapp_settings_updated', $request);

        return $controller->updateSettings($request);
    }

    public function updateWhatsappAutomation(Request $request, WhatsAppIntegrationController $controller)
    {
        $this->audit('ad_manager_whatsapp_automation_updated', $request);

        return $controller->updateAutomation($request);
    }

    public function verifyWhatsapp(Request $request, WhatsAppIntegrationController $controller)
    {
        $this->audit('ad_manager_whatsapp_connection_verified', $request);

        return $controller->verifyConnection($request);
    }

    public function testWhatsapp(Request $request, WhatsAppIntegrationController $controller)
    {
        $this->audit('ad_manager_whatsapp_test_sent', $request);

        return $controller->testMessage($request);
    }

    public function whatsappDebug(Request $request, WhatsAppDebugController $controller)
    {
        return $controller->testConnection($request);
    }

    public function whatsappDebugPost(Request $request, WhatsAppDebugController $controller)
    {
        $this->audit('ad_manager_whatsapp_debug_post_tested', $request);

        return $controller->testPostEndpoint($request);
    }

    public function whatsappDebugCurl(Request $request, WhatsAppDebugController $controller)
    {
        $this->audit('ad_manager_whatsapp_debug_curl_tested', $request);

        return $controller->testRawCurl($request);
    }

    public function whatsappQuickTest(WhatsAppTestController $controller)
    {
        return $controller->quickTest();
    }

    public function sendWhatsappQuickTest(Request $request, WhatsAppTestController $controller)
    {
        $this->audit('ad_manager_whatsapp_quick_test_sent', $request);

        return $controller->sendQuickTest($request);
    }

    public function waba(MetaWabaIntegrationController $controller)
    {
        return $controller->index();
    }

    public function updateWaba(Request $request, MetaWabaIntegrationController $controller)
    {
        $this->audit('ad_manager_waba_settings_updated', $request);

        return $controller->update($request);
    }

    public function startWabaEmbeddedSignup(MetaWabaIntegrationController $controller)
    {
        $this->audit('ad_manager_waba_embedded_signup_started', request());

        return $controller->startEmbeddedSignup();
    }

    public function finishWabaEmbeddedSignup(Request $request, MetaWabaIntegrationController $controller)
    {
        $this->audit('ad_manager_waba_embedded_signup_finished', $request);

        return $controller->finishEmbeddedSignup($request);
    }

    public function disconnectWaba(MetaWabaIntegrationController $controller)
    {
        $this->audit('ad_manager_waba_disconnected', request());

        return $controller->disconnect();
    }

    public function setDefaultWaba(Request $request, MetaWabaIntegrationController $controller)
    {
        $this->audit('ad_manager_waba_default_updated', $request);

        return $controller->setDefault($request);
    }

    public function verifyWaba(Request $request, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_verified', $request);

        return $controller->verify($request, $service);
    }

    public function registerWabaPhone(Request $request, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_phone_registered', $request);

        return $controller->registerPhone($request, $service);
    }

    public function createWabaTemplate(Request $request, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_template_created', $request);

        return $controller->createTemplate($request, $service);
    }

    public function syncWabaTemplates(Request $request, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_templates_synced', $request);

        return $controller->syncTemplates($request, $service);
    }

    public function deleteWabaTemplate(Request $request, WhatsAppTemplate $template, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_template_deleted', $request, ['template_id' => $template->id]);

        return $controller->deleteTemplate($request, $template, $service);
    }

    public function testWabaTemplate(Request $request, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_template_tested', $request);

        return $controller->testTemplate($request, $service);
    }

    public function refreshWabaCallSettings(Request $request, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_call_settings_refreshed', $request);

        return $controller->refreshCallSettings($request, $service);
    }

    public function updateWabaCallSettings(Request $request, MetaWabaIntegrationController $controller, MetaWabaApiService $service)
    {
        $this->audit('ad_manager_waba_call_settings_updated', $request);

        return $controller->updateCallSettings($request, $service);
    }

    public function previewWabaCampaign(Request $request, MetaWabaIntegrationController $controller, WabaCampaignService $service)
    {
        return $controller->previewCampaign($request, $service);
    }

    public function storeWabaCampaign(Request $request, MetaWabaIntegrationController $controller, WabaCampaignService $service)
    {
        $this->audit('ad_manager_waba_campaign_created', $request);

        return $controller->storeCampaign($request, $service);
    }

    private function audit(string $action, Request $request, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'AdManagerMetaOps',
            'model_id' => auth()->id(),
            'description' => 'Ad Manager Meta Ops action: ' . $action,
            'new_values' => array_merge($metadata, [
                'route' => optional($request->route())->getName(),
                'payload_keys' => array_keys($request->except(['password', 'token', 'access_token', 'api_token', '_token'])),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
