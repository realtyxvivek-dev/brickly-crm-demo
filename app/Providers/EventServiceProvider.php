<?php

namespace App\Providers;

use App\Events\LeadAssigned;
use App\Events\LeadCreated;
use App\Events\LeadStatusUpdated;
use App\Events\ProspectSentForVerification;
use App\Events\SiteVisitCreated;
use App\Events\CallLogCreated;
use App\Listeners\CreateManagerExecutiveTask;
use App\Listeners\CreateManagerVerificationCallTask;
use App\Listeners\CreateSiteVisitCallTask;
use App\Listeners\CreateTelecallerTask;
use App\Listeners\DispatchWhatsAppLeadAssignedAutomation;
use App\Listeners\DispatchWhatsAppLeadStatusAutomation;
use App\Listeners\DispatchWhatsAppSiteVisitAutomation;
use App\Listeners\InitiateMcubeCallOnLeadAssigned;
use App\Listeners\SendNewLeadNotification;
use App\Listeners\SendVerificationNotification;
use App\Listeners\SendFollowupNotification;
use App\Listeners\SendSiteVisitCreatedNotification;
use App\Listeners\UpdateGoogleSheetOnStatusChange;
use App\Listeners\UpdateGoogleSheetOnLeadCreated;
use App\Listeners\UpdateGoogleSheetOnLeadAssigned;
use App\Listeners\UpdateGoogleSheetOnCallMade;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        LeadAssigned::class => [
            SendNewLeadNotification::class,
            CreateTelecallerTask::class,
            UpdateGoogleSheetOnLeadAssigned::class,
            DispatchWhatsAppLeadAssignedAutomation::class,
            InitiateMcubeCallOnLeadAssigned::class,
        ],
        LeadCreated::class => [
            UpdateGoogleSheetOnLeadCreated::class,
        ],
        LeadStatusUpdated::class => [
            UpdateGoogleSheetOnStatusChange::class,
            DispatchWhatsAppLeadStatusAutomation::class,
        ],
        CallLogCreated::class => [
            UpdateGoogleSheetOnCallMade::class,
        ],
        SiteVisitCreated::class => [
            SendSiteVisitCreatedNotification::class,
            CreateSiteVisitCallTask::class,
            DispatchWhatsAppSiteVisitAutomation::class,
        ],
        ProspectSentForVerification::class => [
            CreateManagerVerificationCallTask::class,
            CreateManagerExecutiveTask::class,
            SendVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
