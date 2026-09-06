<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\IvrLeadAutomationConfig;
use App\Models\IvrLeadAutomationReceiverOverride;
use App\Services\IvrLeadAutomationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IvrLeadAutomationController extends Controller
{
    public function __construct(
        private readonly IvrLeadAutomationService $service
    ) {
    }

    public function index()
    {
        $config = $this->service->getConfigForUi();
        $summary = $this->service->getDashboardSummary();
        $assignableUsers = $this->service->getAssignableUsers();
        $receiverUsers = $this->service->getReceiverUsers();

        return view('crm.automation.ivr-lead-distribution', compact('config', 'summary', 'assignableUsers', 'receiverUsers'));
    }

    public function update(Request $request)
    {
        $assignableIds = $this->service->getAssignableUsers()->pluck('id')->all();
        $receiverIds = $this->service->getReceiverUsers()->pluck('id')->all();

        $data = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'default_mode' => ['required', Rule::in(array_keys(IvrLeadAutomationConfig::modeOptions()))],
            'distribution_method' => ['required', Rule::in(array_keys(IvrLeadAutomationConfig::methodOptions()))],
            'fallback_mode' => ['required', Rule::in(array_keys(IvrLeadAutomationConfig::fallbackOptions()))],
            'fallback_user_id' => ['nullable', Rule::in($assignableIds)],
            'fixed_user_id' => ['nullable', Rule::in($assignableIds)],
            'default_team_manager_user_id' => ['nullable', Rule::in($receiverIds)],
            'receiver_assignment_allowed' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'pool_users' => 'nullable|array',
            'pool_users.*.user_id' => ['nullable', Rule::in($assignableIds)],
            'pool_users.*.allocation_percentage' => 'nullable|numeric|min:0|max:100',
            'receiver_overrides' => 'nullable|array',
            'receiver_overrides.*.user_id' => ['nullable', Rule::in($receiverIds)],
            'receiver_overrides.*.is_enabled' => 'nullable|boolean',
            'receiver_overrides.*.can_assign_to_self' => 'nullable|boolean',
            'receiver_overrides.*.target_type' => ['required_with:receiver_overrides.*.user_id', Rule::in(array_keys(IvrLeadAutomationReceiverOverride::targetOptions()))],
            'receiver_overrides.*.distribution_method' => ['required_with:receiver_overrides.*.user_id', Rule::in(array_keys(IvrLeadAutomationConfig::methodOptions()))],
            'receiver_overrides.*.team_manager_user_id' => ['nullable', Rule::in($receiverIds)],
            'receiver_overrides.*.fixed_user_id' => ['nullable', Rule::in($assignableIds)],
            'receiver_overrides.*.fallback_mode' => ['nullable', Rule::in(array_keys(IvrLeadAutomationConfig::fallbackOptions()))],
            'receiver_overrides.*.fallback_user_id' => ['nullable', Rule::in($assignableIds)],
            'receiver_overrides.*.notes' => 'nullable|string',
        ]);

        $this->service->saveConfiguration($data, $request->user()->id);

        return redirect()->route('crm.automation.ivr.index')->with('success', 'IVR lead distribution automation updated.');
    }
}
