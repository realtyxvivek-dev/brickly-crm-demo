<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\PhonePrivacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadCommunicationController extends Controller
{
    public function whatsappDirect(Request $request, Lead $lead, PhonePrivacyService $privacy): JsonResponse
    {
        $user = $request->user();
        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to contact this lead.'], 403);
        }

        if ($privacy->isWhatsAppApiOnly($user)) {
            $privacy->audit('blocked_direct_whatsapp', $user, $user, $lead, null, null, null, $request);
            return response()->json([
                'success' => false,
                'message' => 'Direct WhatsApp is blocked. Use CRM WhatsApp API conversation.',
            ], 403);
        }

        $validated = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);
        $digits = preg_replace('/\D+/', '', $privacy->rawLeadPhone($lead));
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }
        if ($digits === '') {
            return response()->json(['success' => false, 'message' => 'Customer phone number is missing.'], 422);
        }

        $privacy->audit('direct_whatsapp_revealed', $user, $user, $lead, null, null, null, $request);
        $message = trim((string) ($validated['message'] ?? ''));

        return response()->json([
            'success' => true,
            'url' => 'https://wa.me/' . $digits . ($message !== '' ? '?text=' . rawurlencode($message) : ''),
        ]);
    }

    private function canAccessLead($user, Lead $lead): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        return $lead->activeAssignments()->where('assigned_to', $user->id)->exists();
    }
}
