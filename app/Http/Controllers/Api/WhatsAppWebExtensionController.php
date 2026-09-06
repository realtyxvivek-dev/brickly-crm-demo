<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppWebExtensionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsAppWebExtensionController extends Controller
{
    public function __construct(
        private readonly WhatsAppWebExtensionService $service,
    ) {
    }

    public function lookup(Request $request)
    {
        $user = $request->user();
        abort_unless($this->isEligibleUser($user), 403, 'You are not allowed to use the WhatsApp Web extension.');

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->buildLookupPayload($validated['phone']),
        ]);
    }

    public function process(Request $request)
    {
        $user = $request->user();
        abort_unless($this->isEligibleUser($user), 403, 'You are not allowed to use the WhatsApp Web extension.');

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'message_preview' => ['nullable', 'string', 'max:2000'],
            'message_timestamp' => ['nullable', 'date'],
            'session_key' => ['nullable', 'string', 'max:120'],
            'event_hash' => ['nullable', 'string', 'max:120'],
            'mode' => ['nullable', Rule::in(['assist', 'auto'])],
        ]);

        $result = $this->service->processInboundMessage($user, $validated);

        return response()->json([
            'success' => $result['status'] !== 'invalid',
            'data' => $result,
        ], $result['status'] === 'invalid' ? 422 : 200);
    }

    private function isEligibleUser($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isAdmin()
            || $user->isCrm()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive();
    }
}
