<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\FbForm;
use App\Models\Lead;
use App\Models\NewLeadSlaAutomationConfig;
use App\Models\Role;
use App\Models\User;
use App\Services\NewLeadSlaAutomationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NewLeadSlaAutomationController extends Controller
{
    public function __construct(
        private readonly NewLeadSlaAutomationService $service
    ) {
    }

    public function index()
    {
        $configs = $this->service->getConfigsForUi();
        $summary = $this->service->getDashboardSummary();

        $salesUsers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::SALES_EXECUTIVE))
            ->orderBy('name')
            ->get();

        $recipients = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [Role::ADMIN, Role::CRM]))
            ->orderBy('name')
            ->get();

        $sources = Lead::sourceOptions();
        $metaForms = FbForm::query()
            ->where('is_enabled', true)
            ->orderBy('form_name')
            ->get(['id', 'form_name', 'form_id']);

        return view('crm.automation.new-lead-sla', compact('configs', 'summary', 'salesUsers', 'recipients', 'sources', 'metaForms'));
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $this->service->storeConfig($data, $request->user()->id);

        return redirect()->route('crm.automation.sla.index')->with('success', 'New lead SLA automation config created.');
    }

    public function update(Request $request, NewLeadSlaAutomationConfig $config)
    {
        $data = $this->validatePayload($request, $config);
        $this->service->updateConfig($config, $data, $request->user()->id);

        return redirect()->route('crm.automation.sla.index')->with('success', 'New lead SLA automation config updated.');
    }

    public function destroy(NewLeadSlaAutomationConfig $config)
    {
        $this->service->deleteConfig($config);

        return redirect()->route('crm.automation.sla.index')->with('success', 'New lead SLA automation config deleted.');
    }

    private function validatePayload(Request $request, ?NewLeadSlaAutomationConfig $config = null): array
    {
        $source = Lead::normalizeSource($request->input('source'));
        $sourceRules = [
            'required',
            'in:' . implode(',', array_keys(Lead::sourceOptions())),
        ];

        if ($source !== 'meta') {
            $sourceRules[] = Rule::unique('new_lead_sla_automation_configs', 'source')
                ->where(fn ($query) => $query->whereNull('fb_form_id'))
                ->ignore($config?->id);
        }

        return $request->validate([
            'name' => 'nullable|string|max:255',
            'source' => $sourceRules,
            'fb_form_id' => [
                Rule::requiredIf($source === 'meta'),
                'nullable',
                'integer',
                'exists:fb_forms,id',
                Rule::unique('new_lead_sla_automation_configs', 'fb_form_id')
                    ->where(fn ($query) => $query->where('source', 'meta'))
                    ->ignore($config?->id),
            ],
            'is_active' => 'nullable|boolean',
            'sla_minutes' => 'required|integer|min:5|max:10080',
            'business_start_time' => 'required|date_format:H:i',
            'business_end_time' => 'required|date_format:H:i|after:business_start_time',
            'weekends_off' => 'nullable|boolean',
            'max_transfer_attempts' => 'required|integer|min:1|max:10',
            'email_enabled' => 'nullable|boolean',
            'in_app_enabled' => 'nullable|boolean',
            'dashboard_alert_enabled' => 'nullable|boolean',
            'skip_inactive_users' => 'nullable|boolean',
            'skip_absent_users' => 'nullable|boolean',
            'pool_user_ids' => 'required|array|min:1',
            'pool_user_ids.*' => 'integer|exists:users,id',
            'recipient_user_ids' => 'required|array|min:1',
            'recipient_user_ids.*' => 'integer|exists:users,id',
        ]);
    }
}
