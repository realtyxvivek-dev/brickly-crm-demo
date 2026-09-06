<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CollateralService;
use App\Helpers\ContactHelper;
use Illuminate\Http\Request;

class ProjectDetailController extends Controller
{
    protected $collateralService;

    public function __construct(CollateralService $collateralService)
    {
        $this->collateralService = $collateralService;
    }

    /**
     * Get project detail with all related data.
     */
    public function show(Project $project)
    {
        $project->load([
            'builder',
            'projectContacts.builderContact',
            'pricingConfig',
            'unitTypes',
            'collaterals',
        ]);

        // Format contacts for call/WhatsApp
        $contacts = $this->formatContacts($project);

        // Get collaterals as buttons
        $collateralButtons = $this->collateralService->generateButtonData($project);

        return response()->json([
            'project' => $project,
            'contacts' => $contacts,
            'collateral_buttons' => $collateralButtons,
        ]);
    }

    /**
     * Format contacts for call/WhatsApp.
     */
    protected function formatContacts(Project $project): array
    {
        $contacts = [];

        $primary = $project->primaryContact();
        if ($primary) {
            $builderContact = $primary->builderContact;
            $contacts['primary'] = [
                'id' => $builderContact->id,
                'person_name' => $builderContact->person_name,
                'mobile_number' => $builderContact->mobile_number,
                'whatsapp_number' => $builderContact->getEffectiveWhatsAppNumber(),
                'call_url' => ContactHelper::getCallUrl($builderContact->mobile_number),
                'whatsapp_url' => ContactHelper::getWhatsAppUrl($builderContact->getEffectiveWhatsAppNumber()),
            ];
        }

        $secondary = $project->secondaryContact();
        if ($secondary) {
            $builderContact = $secondary->builderContact;
            $contacts['secondary'] = [
                'id' => $builderContact->id,
                'person_name' => $builderContact->person_name,
                'mobile_number' => $builderContact->mobile_number,
                'whatsapp_number' => $builderContact->getEffectiveWhatsAppNumber(),
                'call_url' => ContactHelper::getCallUrl($builderContact->mobile_number),
                'whatsapp_url' => ContactHelper::getWhatsAppUrl($builderContact->getEffectiveWhatsAppNumber()),
            ];
        }

        $escalation = $project->escalationContact();
        if ($escalation) {
            $builderContact = $escalation->builderContact;
            $contacts['escalation'] = [
                'id' => $builderContact->id,
                'person_name' => $builderContact->person_name,
                'mobile_number' => $builderContact->mobile_number,
                'whatsapp_number' => $builderContact->getEffectiveWhatsAppNumber(),
                'call_url' => ContactHelper::getCallUrl($builderContact->mobile_number),
                'whatsapp_url' => ContactHelper::getWhatsAppUrl($builderContact->getEffectiveWhatsAppNumber()),
            ];
        }

        return $contacts;
    }
}
