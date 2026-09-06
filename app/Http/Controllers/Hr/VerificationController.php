<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\SiteVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function index()
    {
        return view('hr-manager.verifications', [
            'api_token' => auth()->user()->createToken('hr-verification-token')->plainTextToken,
        ]);
    }

    public function verified(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $relations = ['lead:id,name,phone', 'creator:id,name', 'assignedTo:id,name', 'verifiedBy:id,name'];

        $meetings = Meeting::query()
            ->where('verification_status', 'verified')
            ->where('verified_by', $userId)
            ->with($relations)
            ->latest('verified_at')
            ->limit(100)
            ->get()
            ->map(fn (Meeting $meeting) => $this->verifiedPayload($meeting, 'meeting'));

        $siteVisits = SiteVisit::query()
            ->where('verification_status', 'verified')
            ->where('verified_by', $userId)
            ->with($relations)
            ->latest('verified_at')
            ->limit(100)
            ->get()
            ->map(fn (SiteVisit $visit) => $this->verifiedPayload($visit, 'site_visit'));

        $items = $meetings->concat($siteVisits)
            ->sortByDesc('verified_at')
            ->take(100)
            ->values();

        return response()->json([
            'data' => $items->all(),
            'total' => $items->count(),
            'meetings_count' => $items->where('type', 'meeting')->count(),
            'site_visits_count' => $items->where('type', 'site_visit')->count(),
        ]);
    }

    private function verifiedPayload(Meeting|SiteVisit $item, string $type): array
    {
        $photos = collect($item->completion_proof_photos ?? $item->photos ?? [])
            ->filter()
            ->map(function ($path) {
                $path = trim(str_replace('\\', '/', (string) $path));
                if ($path === '') {
                    return null;
                }
                if (filter_var($path, FILTER_VALIDATE_URL)) {
                    $urlPath = parse_url($path, PHP_URL_PATH);
                    if (!is_string($urlPath) || !preg_match('#/(?:public/)?storage/#i', $urlPath)) {
                        return $path;
                    }
                    $path = $urlPath;
                }

                $path = preg_replace('#^/?(?:public/)?storage/#i', '', $path);
                $path = preg_replace('#^/?public/#i', '', (string) $path);
                $path = preg_replace('#^storage/#i', '', (string) $path);

                return route('storage.proxy', ['path' => ltrim((string) $path, '/')]);
            })
            ->filter()
            ->values()
            ->all();

        return [
            'type' => $type,
            'id' => $item->id,
            'customer_name' => $item->customer_name ?: $item->lead?->name,
            'phone' => $item->phone ?: $item->lead?->phone,
            'completed_at' => optional($item->completed_at)->toIso8601String(),
            'scheduled_at' => optional($item->scheduled_at)->toIso8601String(),
            'verified_at' => optional($item->verified_at)->toIso8601String(),
            'status' => $item->status,
            'verification_status' => $item->verification_status,
            'project' => $item->project,
            'property_name' => $type === 'site_visit' ? $item->property_name : null,
            'property_address' => $type === 'site_visit' ? $item->property_address : null,
            'budget_range' => $item->budget_range,
            'property_type' => $item->property_type,
            'notes' => $type === 'meeting' ? $item->meeting_notes : $item->visit_notes,
            'proof_photos' => $photos,
            'lead' => $item->lead ? ['id' => $item->lead->id, 'name' => $item->lead->name, 'phone' => $item->lead->phone] : null,
            'creator' => $item->creator ? ['id' => $item->creator->id, 'name' => $item->creator->name] : null,
            'assignedTo' => $item->assignedTo ? ['id' => $item->assignedTo->id, 'name' => $item->assignedTo->name] : null,
            'verifiedBy' => $item->verifiedBy ? ['id' => $item->verifiedBy->id, 'name' => $item->verifiedBy->name] : null,
        ];
    }
}
