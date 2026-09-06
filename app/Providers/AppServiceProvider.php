<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Task;
use App\Models\Lead;
use App\Models\LeadFormFieldValue;
use App\Models\Incentive;
use App\Models\PricingConfig;
use App\Models\SiteVisit;
use App\Models\UnitType;
use App\Observers\UserObserver;
use App\Observers\TaskObserver;
use App\Observers\LeadObserver;
use App\Observers\LeadFormFieldValueObserver;
use App\Observers\IncentiveObserver;
use App\Observers\PricingConfigObserver;
use App\Observers\SiteVisitObserver;
use App\Observers\UnitTypeObserver;
use App\Services\TravelTime\Contracts\LocationSearchProvider;
use App\Services\TravelTime\Contracts\RouteProvider;
use App\Services\MailSettingsService;
use App\Services\PhonePrivacyService;
use App\Services\TravelTime\Providers\OlaLocationSearchProvider;
use App\Services\TravelTime\Providers\OlaRouteProvider;
use App\Support\AppUrl;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LocationSearchProvider::class, OlaLocationSearchProvider::class);
        $this->app->bind(RouteProvider::class, OlaRouteProvider::class);
        $this->app->singleton(PhonePrivacyService::class);
    }

    public function boot(): void
    {
        app(MailSettingsService::class)->apply();

        $configuredAppUrl = trim((string) config('app.url', ''));
        if ($configuredAppUrl !== '') {
            URL::forceRootUrl(AppUrl::root());

            $scheme = parse_url($configuredAppUrl, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                URL::forceScheme($scheme);
            }
        }

        // Register User Observer for manager change detection
        User::observe(UserObserver::class);

        // Register Task Observer for activity logging
        Task::observe(TaskObserver::class);
        foreach ([Task::class, \App\Models\TelecallerTask::class, \App\Models\FollowUp::class, \App\Models\Meeting::class, SiteVisit::class, \App\Models\Prospect::class, \App\Models\CrmAssignment::class] as $recordClass) {
            $recordClass::observe(\App\Observers\LeadCycleRecordObserver::class);
        }

        Lead::observe(LeadObserver::class);
        LeadFormFieldValue::observe(LeadFormFieldValueObserver::class);
        SiteVisit::observe(SiteVisitObserver::class);
        Incentive::observe(IncentiveObserver::class);

        // Register Pricing and Unit Type Observers
        PricingConfig::observe(PricingConfigObserver::class);
        UnitType::observe(UnitTypeObserver::class);

        // Ensure PHP upload temp dir is writable (prevents Request::Startup warnings)
        $uploadTmpDir = storage_path('app/tmp');
        if (!is_dir($uploadTmpDir)) {
            File::ensureDirectoryExists($uploadTmpDir);
        }
        if (is_dir($uploadTmpDir) && is_writable($uploadTmpDir)) {
            ini_set('upload_tmp_dir', $uploadTmpDir);
        }
    }
}
