<?php

namespace App\Jobs;

use App\Models\FbForm;
use App\Models\FbLead;
use App\Models\FbWebhookEvent;
use App\Models\Lead;
use App\Models\MetaBulkRecoveryScanLead;
use App\Models\User;
use App\Services\FacebookGraphService;
use App\Services\FacebookLeadMappingService;
use App\Services\LeadDuplicateGuardService;
use App\Services\SourceAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class FetchFacebookLeadDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(
        public string $leadgenId,
        public int $fbFormId,
        public bool $autoAssign = true,
        public ?int $bulkRecoveryScanLeadId = null
    ) {
        $this->onQueue('meta');
    }

    public function handle(): void
    {
        $existingFbLead = FbLead::where('leadgen_id', $this->leadgenId)->first();
        if ($existingFbLead && $existingFbLead->crm_lead_id) {
            FbWebhookEvent::where('leadgen_id', $this->leadgenId)
                ->where('status', 'received')
                ->update(['status' => 'processed']);
            $this->markBulkRecoveryLead('already_present');

            return;
        }

        $fbForm = FbForm::with(['page', 'mapping'])->find($this->fbFormId);
        if (!$fbForm || !$fbForm->page) {
            $this->failEvent('Configured form/page missing');
            return;
        }

        if (!$fbForm->is_enabled) {
            FbWebhookEvent::where('leadgen_id', $this->leadgenId)
                ->where('status', 'received')
                ->update([
                    'status' => 'ignored_disabled',
                    'error' => 'Form disabled: ' . $fbForm->form_id,
                ]);

            return;
        }

        if ($existingFbLead) {
            $fieldData = $existingFbLead->raw_response_json['field_data'] ?? null;
            if (!is_array($fieldData) || empty($fieldData)) {
                $fieldData = $this->flatFieldDataToMetaFieldData($existingFbLead->field_data_json ?? []);
            }

            $mappingService = new FacebookLeadMappingService();
            $crmLeadResult = $this->createCrmLead($fbForm, $mappingService, $fieldData);
            $lead = $crmLeadResult['lead'] ?? null;
            if (!$lead) {
                $this->failEvent('Existing Meta lead row found, but CRM lead was not created.');
                return;
            }

            $existingFbLead->update(['crm_lead_id' => $lead->id]);

            if (($crmLeadResult['was_created'] ?? false) === true && $this->autoAssign) {
                $this->assignCreatedLead($lead);
            }
            $this->markBulkRecoveryLead('imported');

            FbWebhookEvent::where('leadgen_id', $this->leadgenId)
                ->whereIn('status', ['received', 'failed', 'processed'])
                ->update(['status' => 'processed', 'error' => null]);

            return;
        }

        $token = $fbForm->page->page_access_token;
        if (empty($token)) {
            $this->failEvent('No token for page');
            return;
        }

        $settings = \App\Models\FbLeadAdsSettings::getSettings();
        $graphVersion = $settings->graph_version ?? 'v18.0';
        $client = FacebookGraphService::fromToken($token, $graphVersion);
        $result = $client->getLeadDetails($this->leadgenId);

        if (!$result['success']) {
            $this->failEvent($result['error'] ?? 'Unknown error');
            return;
        }

        $data = $result['data'];
        $fieldData = $data['field_data'] ?? [];

        $mappingService = new FacebookLeadMappingService();
        $flatFieldData = $mappingService->fieldDataToFlat($fieldData);

        $mappingJson = $fbForm->mapping?->mapping_json ?? [];
        $usedFallbackMapping = empty($mappingJson);

        $rawResponse = $data;
        if ($usedFallbackMapping) {
            $rawResponse['_crm_import_mode'] = 'fallback_mapping';
        }

        $fbLead = FbLead::create([
            'leadgen_id' => $this->leadgenId,
            'fb_form_id' => $this->fbFormId,
            'ad_id' => $data['ad_id'] ?? null,
            'ad_name' => $data['ad_name'] ?? null,
            'adset_id' => $data['adset_id'] ?? null,
            'adset_name' => $data['adset_name'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'campaign_name' => $data['campaign_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'meta_created_time' => !empty($data['created_time']) ? Carbon::parse($data['created_time']) : null,
            'field_data_json' => $flatFieldData,
            'raw_response_json' => $rawResponse,
        ]);

        // CRM lead auto-create
        $crmLeadResult = $this->createCrmLead($fbForm, $mappingService, $fieldData);
        $lead = $crmLeadResult['lead'] ?? null;
        if (!$lead) {
            $this->failEvent('Meta lead fetched, but CRM lead was not created.');
            return;
        }

        $fbLead->update(['crm_lead_id' => $lead->id]);

        // Only brand-new Meta leads should go through the normal initial source automation path.
        if (($crmLeadResult['was_created'] ?? false) === true && $this->autoAssign) {
            $this->assignCreatedLead($lead);
        }
        $this->markBulkRecoveryLead('imported');

        FbWebhookEvent::where('leadgen_id', $this->leadgenId)->where('status', 'received')->update(['status' => 'processed']);
    }

    protected function createCrmLead(FbForm $fbForm, FacebookLeadMappingService $mappingService, array $fieldData): ?array
    {
        try {
            $mappingJson = $fbForm->mapping?->mapping_json ?? [];

            if (empty($mappingJson)) {
                $mappingJson = FacebookLeadMappingService::fallbackMappingForFieldData($fieldData);
            }

            $mapped = $mappingService->applyMapping($fieldData, $mappingJson);
            $createdBy = User::orderBy('id')->value('id') ?? 1;
            return app(LeadDuplicateGuardService::class)->createOrAttachMetaLead($fbForm, $mapped, $createdBy);
        } catch (\Throwable $e) {
            Log::warning('FetchFacebookLeadDetailsJob: CRM lead create failed', [
                'leadgen_id' => $this->leadgenId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function failEvent(string $error): void
    {
        $error = $this->sanitizeErrorMessage($error);

        FbWebhookEvent::where('leadgen_id', $this->leadgenId)->where('status', 'received')->update([
            'status' => 'failed',
            'error'  => $error,
        ]);
        $this->markBulkRecoveryLead('failed', $error);
        Log::warning('FetchFacebookLeadDetailsJob failed', ['leadgen_id' => $this->leadgenId, 'error' => $error]);
    }

    private function markBulkRecoveryLead(string $status, ?string $error = null): void
    {
        if (!$this->bulkRecoveryScanLeadId) {
            return;
        }

        MetaBulkRecoveryScanLead::whereKey($this->bulkRecoveryScanLeadId)->update([
            'status' => $status,
            'error' => $error,
        ]);
    }

    private function assignCreatedLead(Lead $lead): void
    {
        try {
            app(SourceAutomationService::class)->assignFromSource(
                $lead,
                'facebook_lead_ads',
                $this->fbFormId
            );
        } catch (\Throwable $e) {
            Log::warning('FetchFacebookLeadDetailsJob: automation assign failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function flatFieldDataToMetaFieldData(array $flatFieldData): array
    {
        $fieldData = [];
        foreach ($flatFieldData as $name => $value) {
            $fieldData[] = [
                'name' => $name,
                'values' => [$value],
            ];
        }

        return $fieldData;
    }

    private function sanitizeErrorMessage(string $error): string
    {
        $error = preg_replace('/([?&](?:access_token|client_secret|appsecret_proof)=)[^&\\s]+/i', '$1[redacted]', $error) ?? $error;
        $error = preg_replace('/(Bearer\\s+)[A-Za-z0-9._\\-]+/i', '$1[redacted]', $error) ?? $error;

        return $error;
    }
}
