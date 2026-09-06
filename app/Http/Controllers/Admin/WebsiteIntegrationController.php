<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteIntegration;
use App\Services\FieldMappingService;
use App\Services\WebsiteLeadIntakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class WebsiteIntegrationController extends Controller
{
    public function __construct(
        private readonly FieldMappingService $fieldMappingService,
        private readonly WebsiteLeadIntakeService $websiteLeadIntakeService,
    ) {
    }

    public function index()
    {
        $integrations = WebsiteIntegration::with(['fieldMappings', 'fallbackUser'])
            ->where('created_by', auth()->id())
            ->latest()
            ->get();

        return view('integrations.website.index', compact('integrations'));
    }

    public function create()
    {
        $templates = $this->fieldMappingService->getWebsiteIntegrationTemplates();
        $defaultTemplate = $templates['website_default_json'];

        return view('integrations.website.form', [
            'integration' => new WebsiteIntegration([
                'is_active' => true,
                'fallback_type' => WebsiteIntegration::FALLBACK_UNASSIGNED_CRM_QUEUE,
                'rate_limit' => 60,
                'sample_payload_json' => $defaultTemplate['sample_payload'],
            ]),
            'mappings' => $defaultTemplate['mappings'],
            'templates' => $templates,
            'targetFields' => $this->fieldMappingService->getWebsiteTargetFields(),
            'fallbackOptions' => WebsiteIntegration::fallbackOptions(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'logs' => collect(),
            'queueLeads' => collect(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateIntegration($request);
        $samplePayload = $this->parseSamplePayload($validated['sample_payload_json']);
        $validation = $this->fieldMappingService->validateWebsiteMappings($validated['mappings'], $samplePayload);

        if (!empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->withInput();
        }

        $integration = WebsiteIntegration::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_active' => $request->boolean('is_active'),
            'source' => 'website',
            'default_status' => 'new',
            'api_key' => WebsiteIntegration::generateApiKey(),
            'description' => $validated['description'] ?? null,
            'fallback_type' => $validated['fallback_type'],
            'fallback_user_id' => $validated['fallback_type'] === WebsiteIntegration::FALLBACK_DEFAULT_USER
                ? ($validated['fallback_user_id'] ?? null)
                : null,
            'rate_limit' => $validated['rate_limit'],
            'sample_payload_json' => $samplePayload,
            'created_by' => auth()->id(),
        ]);

        $this->syncMappings($integration, $validated['mappings']);

        return redirect()->route('integrations.website.edit', $integration)->with('success', 'Website integration created.');
    }

    public function edit(WebsiteIntegration $website)
    {
        abort_unless($website->created_by === auth()->id(), 403);

        return view('integrations.website.form', [
            'integration' => $website->load(['fieldMappings', 'requestLogs.lead', 'fallbackUser']),
            'mappings' => $website->fieldMappings->map(fn ($mapping) => Arr::only($mapping->toArray(), [
                'incoming_field',
                'crm_field',
                'is_required',
                'default_value',
                'is_ignored',
            ]))->values()->all(),
            'templates' => $this->fieldMappingService->getWebsiteIntegrationTemplates(),
            'targetFields' => $this->fieldMappingService->getWebsiteTargetFields(),
            'fallbackOptions' => WebsiteIntegration::fallbackOptions(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'logs' => $website->requestLogs()->with('lead')->latest()->limit(20)->get(),
            'queueLeads' => $website->leads()->where('website_queue_status', 'unassigned_website')->latest()->limit(20)->get(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, WebsiteIntegration $website)
    {
        abort_unless($website->created_by === auth()->id(), 403);

        $validated = $this->validateIntegration($request, $website->id);
        $samplePayload = $this->parseSamplePayload($validated['sample_payload_json']);
        $validation = $this->fieldMappingService->validateWebsiteMappings($validated['mappings'], $samplePayload);

        if (!empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->withInput();
        }

        $website->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_active' => $request->boolean('is_active'),
            'description' => $validated['description'] ?? null,
            'fallback_type' => $validated['fallback_type'],
            'fallback_user_id' => $validated['fallback_type'] === WebsiteIntegration::FALLBACK_DEFAULT_USER
                ? ($validated['fallback_user_id'] ?? null)
                : null,
            'rate_limit' => $validated['rate_limit'],
            'sample_payload_json' => $samplePayload,
        ]);

        $this->syncMappings($website, $validated['mappings']);

        return redirect()->route('integrations.website.edit', $website)->with('success', 'Website integration updated.');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'sample_payload_json' => 'required|string',
            'mappings' => 'required|array',
        ]);

        $samplePayload = $this->parseSamplePayload($request->input('sample_payload_json'));
        $validation = $this->fieldMappingService->validateWebsiteMappings($request->input('mappings'), $samplePayload);

        return response()->json([
            'success' => empty($validation['errors']),
            'preview' => $validation['preview'],
            'errors' => $validation['errors'],
        ]);
    }

    public function test(Request $request, WebsiteIntegration $website)
    {
        abort_unless($website->created_by === auth()->id(), 403);

        $request->validate([
            'sample_payload_json' => 'required|string',
            'mappings' => 'nullable|array',
        ]);

        $payload = $this->parseSamplePayload($request->input('sample_payload_json'));

        $result = $this->websiteLeadIntakeService->handle(
            $website->fresh(['fieldMappings']),
            $payload,
            true,
            $request->ip(),
            $request->input('mappings')
        );

        return response()->json($result, $result['http_status']);
    }

    public function templates()
    {
        return response()->json([
            'success' => true,
            'templates' => $this->fieldMappingService->getWebsiteIntegrationTemplates(),
        ]);
    }

    public function regenerateApiKey(WebsiteIntegration $website)
    {
        abort_unless($website->created_by === auth()->id(), 403);

        $website->update([
            'api_key' => WebsiteIntegration::generateApiKey(),
        ]);

        return response()->json([
            'success' => true,
            'api_key' => $website->api_key,
        ]);
    }

    private function validateIntegration(Request $request, ?int $websiteId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9-]+$/|unique:website_integrations,slug,' . ($websiteId ?: 'NULL') . ',id',
            'description' => 'nullable|string',
            'fallback_type' => 'required|in:' . implode(',', array_keys(WebsiteIntegration::fallbackOptions())),
            'fallback_user_id' => 'nullable|exists:users,id',
            'rate_limit' => 'required|integer|min:1|max:5000',
            'sample_payload_json' => 'required|string',
            'mappings' => 'required|array|min:1',
            'mappings.*.incoming_field' => 'required|string|max:255',
            'mappings.*.crm_field' => 'nullable|string|max:255',
            'mappings.*.is_required' => 'nullable|boolean',
            'mappings.*.default_value' => 'nullable|string',
            'mappings.*.is_ignored' => 'nullable|boolean',
        ]);
    }

    private function parseSamplePayload(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'sample_payload_json' => 'Sample payload must be valid JSON.',
            ]);
        }

        return $decoded;
    }

    private function syncMappings(WebsiteIntegration $integration, array $mappings): void
    {
        $integration->fieldMappings()->delete();

        foreach (array_values($mappings) as $index => $mapping) {
            $integration->fieldMappings()->create([
                'incoming_field' => trim((string) ($mapping['incoming_field'] ?? '')),
                'crm_field' => blank($mapping['crm_field'] ?? null) ? null : trim((string) $mapping['crm_field']),
                'is_required' => (bool) ($mapping['is_required'] ?? false),
                'default_value' => $mapping['default_value'] ?? null,
                'is_ignored' => (bool) ($mapping['is_ignored'] ?? false),
                'display_order' => $index + 1,
            ]);
        }
    }
}
