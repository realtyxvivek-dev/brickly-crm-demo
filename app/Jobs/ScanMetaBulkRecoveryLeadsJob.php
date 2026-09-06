<?php

namespace App\Jobs;

use App\Models\FbForm;
use App\Models\FbLead;
use App\Models\MetaBulkRecoveryScan;
use App\Models\MetaBulkRecoveryScanForm;
use App\Services\FacebookGraphService;
use App\Services\FacebookLeadMappingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanMetaBulkRecoveryLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $scanId)
    {
        $this->onQueue('meta');
    }

    public function handle(): void
    {
        $lock = Cache::lock('meta-bulk-recovery-active', 900);
        if (!$lock->get()) {
            $this->release(60);
            return;
        }

        try {
            $scan = MetaBulkRecoveryScan::find($this->scanId);
            if (!$scan || !in_array($scan->status, ['queued', 'scanning'], true)) {
                return;
            }

            $scan->update([
                'status' => 'scanning',
                'started_at' => $scan->started_at ?: now(),
                'error' => null,
            ]);

            $forms = $this->formsForScan($scan);
            $scan->update(['forms_total' => $forms->count()]);

            $remaining = (int) $scan->total_limit;
            foreach ($forms as $form) {
                if ($remaining <= 0) {
                    break;
                }

                $scanForm = MetaBulkRecoveryScanForm::firstOrCreate(
                    ['scan_id' => $scan->id, 'fb_form_id' => $form->id],
                    ['fb_page_id' => $form->fb_page_id, 'status' => 'pending']
                );

                $limit = min((int) $scan->per_form_limit, $remaining);
                $this->scanForm($scan, $scanForm, $form, $limit);

                $remaining = max(0, (int) $scan->total_limit - (int) $scan->fresh()->fetched_count);
            }

            $scan->refresh();
            $scan->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Meta bulk recovery scan failed', [
                'scan_id' => $this->scanId,
                'error' => $e->getMessage(),
            ]);

            MetaBulkRecoveryScan::whereKey($this->scanId)->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private function formsForScan(MetaBulkRecoveryScan $scan)
    {
        return FbForm::query()
            ->with(['page.portfolio', 'mapping'])
            ->where('is_enabled', true)
            ->whereHas('page', fn ($query) => $query->whereNotNull('page_access_token'))
            ->when($scan->scope_type === 'portfolio' && $scan->scope_id, function ($query) use ($scan) {
                $query->whereHas('page', fn ($pageQuery) => $pageQuery->where('facebook_portfolio_id', $scan->scope_id));
            })
            ->when($scan->scope_type === 'page' && $scan->scope_id, fn ($query) => $query->where('fb_page_id', $scan->scope_id))
            ->when($scan->scope_type === 'form' && $scan->scope_id, fn ($query) => $query->where('id', $scan->scope_id))
            ->orderBy('fb_page_id')
            ->orderBy('form_name')
            ->get();
    }

    private function scanForm(MetaBulkRecoveryScan $scan, MetaBulkRecoveryScanForm $scanForm, FbForm $form, int $limit): void
    {
        $scanForm->update([
            'status' => 'scanning',
            'error' => null,
        ]);

        try {
            $client = FacebookGraphService::fromToken(
                (string) $form->page->page_access_token,
                \App\Models\FbLeadAdsSettings::getSettings()->graph_version ?? 'v18.0'
            );
            $fetch = $client->getFormLeads(
                (string) $form->form_id,
                Carbon::parse($scan->date_from)->startOfDay()->toIso8601String(),
                Carbon::parse($scan->date_to)->endOfDay()->toIso8601String(),
                $limit
            );

            if (!($fetch['success'] ?? false)) {
                $scanForm->update([
                    'status' => 'failed',
                    'failed_count' => 1,
                    'error' => $this->sanitizeErrorMessage($fetch['error'] ?? 'Meta API failed.'),
                ]);
                $this->recountScan($scan);
                return;
            }

            $leads = collect($fetch['leads'] ?? []);
            $existingLeadgenIds = FbLead::whereIn('leadgen_id', $leads->pluck('id')->filter()->map(fn ($id) => (string) $id))
                ->pluck('leadgen_id')
                ->map(fn ($id) => (string) $id)
                ->flip();

            $mappingService = app(FacebookLeadMappingService::class);
            foreach ($leads as $metaLead) {
                $leadgenId = (string) ($metaLead['id'] ?? '');
                if ($leadgenId === '') {
                    continue;
                }

                $flat = $mappingService->fieldDataToFlat($metaLead['field_data'] ?? []);
                $alreadyPresent = $existingLeadgenIds->has($leadgenId);

                $scanForm->leads()->updateOrCreate(
                    ['scan_id' => $scan->id, 'leadgen_id' => $leadgenId],
                    [
                        'fb_form_id' => $form->id,
                        'name' => $this->firstFlatValue($flat, ['full_name', 'name', 'first_name']),
                        'phone' => $this->firstFlatValue($flat, ['phone_number', 'phone', 'mobile_number', 'mobile']),
                        'email' => $this->firstFlatValue($flat, ['email', 'email_address']),
                        'meta_created_time' => !empty($metaLead['created_time']) ? Carbon::parse($metaLead['created_time']) : null,
                        'campaign_name' => $metaLead['campaign_name'] ?? null,
                        'ad_name' => $metaLead['ad_name'] ?? null,
                        'raw_meta_json' => $metaLead,
                        'status' => $alreadyPresent ? 'already_present' : 'ready',
                        'error' => null,
                    ]
                );
            }

            $scanForm->update([
                'status' => 'completed',
                'fetched_count' => $leads->count(),
                'importable_count' => $scanForm->leads()->where('status', 'ready')->count(),
                'already_present_count' => $scanForm->leads()->where('status', 'already_present')->count(),
                'failed_count' => 0,
                'error' => null,
            ]);
            $this->recountScan($scan);
        } catch (Throwable $e) {
            $scanForm->update([
                'status' => 'failed',
                'failed_count' => 1,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);
            $this->recountScan($scan);
        }
    }

    private function recountScan(MetaBulkRecoveryScan $scan): void
    {
        $scan->load('forms');
        $scan->update([
            'forms_scanned' => $scan->forms()->whereIn('status', ['completed', 'failed'])->count(),
            'fetched_count' => $scan->forms()->sum('fetched_count'),
            'importable_count' => $scan->leads()->where('status', 'ready')->count(),
            'already_present_count' => $scan->leads()->where('status', 'already_present')->count(),
            'failed_count' => $scan->forms()->where('status', 'failed')->count() + $scan->leads()->where('status', 'failed')->count(),
        ]);
    }

    private function firstFlatValue(array $flat, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $flat[$key] ?? null;
            if (is_array($value)) {
                $value = reset($value);
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function sanitizeErrorMessage(string $error): string
    {
        $error = preg_replace('/([?&](?:access_token|client_secret|appsecret_proof)=)[^&\\s]+/i', '$1[redacted]', $error) ?? $error;
        $error = preg_replace('/(Bearer\\s+)[A-Za-z0-9._\\-]+/i', '$1[redacted]', $error) ?? $error;

        return $error;
    }
}
