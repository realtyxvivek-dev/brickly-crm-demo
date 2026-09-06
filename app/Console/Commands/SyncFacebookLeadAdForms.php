<?php

namespace App\Console\Commands;

use App\Models\FbForm;
use App\Models\FbLeadAdsSettings;
use App\Models\FbPage;
use App\Services\FacebookGraphService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncFacebookLeadAdForms extends Command
{
    protected $signature = 'facebook-lead-ads:sync-forms {--page-id= : Internal fb_pages id to sync one page}';

    protected $description = 'Safely sync Facebook lead ad forms without enabling new forms automatically';

    public function handle(): int
    {
        $settings = FbLeadAdsSettings::getSettings();
        $query = FbPage::whereNotNull('page_access_token')->orderBy('page_name');

        $pageId = $this->option('page-id');
        if ($pageId !== null && $pageId !== '') {
            $query->whereKey((int) $pageId);
        }

        $pages = $query->get();
        $synced = 0;
        $created = 0;
        $failed = 0;

        foreach ($pages as $page) {
            try {
                $client = FacebookGraphService::fromToken($page->page_access_token, $settings->graph_version ?? 'v18.0');
                $result = $client->getLeadgenForms((string) $page->page_id);

                if (!($result['success'] ?? false)) {
                    $failed++;
                    $this->warn("Failed: {$page->page_name} - " . ($result['error'] ?? 'Unknown error'));
                    continue;
                }

                foreach (($result['forms'] ?? []) as $formRow) {
                    $formId = (string) ($formRow['id'] ?? '');
                    if ($formId === '') {
                        continue;
                    }

                    $form = FbForm::firstOrNew(['form_id' => $formId]);
                    $isNew = !$form->exists;

                    if ($isNew) {
                        $form->is_enabled = false;
                    }

                    $form->fb_page_id = $page->id;
                    $form->form_name = $formRow['name'] ?? $form->form_name ?? ('Meta Form ' . $formId);
                    $form->save();

                    $synced++;
                    $created += $isNew ? 1 : 0;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Scheduled Facebook lead ad form sync failed', [
                    'fb_page_id' => $page->id,
                    'page_id' => $page->page_id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Failed: {$page->page_name} - {$e->getMessage()}");
            }
        }

        $this->info("Facebook Lead Ads form sync complete. Synced {$synced}, new {$created}, failed pages {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
