<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NinetyNineAcresSetting;
use App\Models\User;
use App\Services\NinetyNineAcresLeadIntakeService;
use Illuminate\Http\Request;

class NinetyNineAcresIntegrationController extends Controller
{
    public function __construct(
        private readonly NinetyNineAcresLeadIntakeService $leadIntakeService,
    ) {
    }

    public function index()
    {
        $settings = NinetyNineAcresSetting::getSettings()->load('fallbackUser');
        $logs = $settings->requestLogs()->with('lead')->limit(30)->get();
        $users = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('integrations.99acres.index', [
            'settings' => $settings,
            'logs' => $logs,
            'users' => $users,
            'fallbackOptions' => NinetyNineAcresSetting::fallbackOptions(),
            'webhookUrl' => url('/api/webhooks/99acres/leads'),
            'samplePayload' => $this->samplePayload(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'fallback_type' => 'required|in:' . implode(',', array_keys(NinetyNineAcresSetting::fallbackOptions())),
            'fallback_user_id' => 'nullable|exists:users,id',
        ]);

        $settings = NinetyNineAcresSetting::getSettings();
        $settings->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'default_status' => 'new',
            'fallback_type' => $validated['fallback_type'],
            'fallback_user_id' => $validated['fallback_type'] === NinetyNineAcresSetting::FALLBACK_DEFAULT_USER
                ? ($validated['fallback_user_id'] ?? null)
                : null,
        ]);

        return back()->with('success', '99acres settings saved.');
    }

    public function regenerateApiKey()
    {
        $settings = NinetyNineAcresSetting::getSettings();
        $settings->update([
            'api_key' => NinetyNineAcresSetting::generateApiKey(),
        ]);

        return back()->with('success', '99acres API key regenerated.');
    }

    public function test(Request $request)
    {
        $payload = $request->validate([
            'payload' => 'required|string',
        ]);

        $decoded = json_decode($payload['payload'], true);
        if (!is_array($decoded)) {
            return back()->withErrors(['payload' => 'Payload must be valid JSON.'])->withInput();
        }

        $settings = NinetyNineAcresSetting::getSettings();
        $result = $this->leadIntakeService->handle($settings, $decoded, true, $request->ip());

        return back()->with($result['status'] === 'ok' ? 'success' : 'error', $result['message']);
    }

    private function samplePayload(): string
    {
        return json_encode([
            'lead_id' => 'optional-external-id',
            'name' => 'Customer Name',
            'phone' => '9876543210',
            'email' => 'customer@example.com',
            'project_name' => 'Project Name',
            'location' => 'Noida',
            'budget' => '80 Lac - 1 Cr',
            'property_type' => '3 BHK',
            'message' => 'Customer requirement',
            'created_at' => now()->format('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
