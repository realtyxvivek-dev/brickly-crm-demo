<?php

namespace App\Http\Controllers;

use App\Models\CallingCenterCampaign;
use App\Models\CallingCenterCampaignItem;
use App\Models\CallingCenterPushRequest;
use App\Models\Lead;
use App\Models\LeadTag;
use App\Models\Role;
use App\Models\User;
use App\Services\CallingCenterService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallingCenterController extends Controller
{
    public function __construct(private readonly CallingCenterService $callingCenterService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canUseCallingCenter('calling_center.view'), 403);

        if ($user->canUseCallingCenter('calling_center.agent_queue') && !$user->canUseCallingCenter('calling_center.create_campaign')) {
            return redirect()->route('calling-center.queue');
        }

        $teamView = $user->canUseCallingCenter('calling_center.view_team_report') || $user->canUseCallingCenter('calling_center.create_campaign');
        $campaigns = CallingCenterCampaign::query()
            ->with(['assignedTo:id,name,phone,role_id', 'creator:id,name'])
            ->withCount([
                'items',
                'items as pending_items_count' => fn ($query) => $query->where('status', CallingCenterCampaignItem::STATUS_PENDING),
                'items as completed_items_count' => fn ($query) => $query->where('status', CallingCenterCampaignItem::STATUS_COMPLETED),
                'items as failed_items_count' => fn ($query) => $query->where('status', CallingCenterCampaignItem::STATUS_FAILED),
                'items as skipped_items_count' => fn ($query) => $query->where('status', CallingCenterCampaignItem::STATUS_SKIPPED),
            ])
            ->when(!$teamView, fn ($query) => $query->where('assigned_to', $user->id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $agents = $this->callingCenterService->eligibleAgents();
        $folderTags = LeadTag::query()->where('is_folder', true)->orderBy('name')->get(['id', 'name']);
        $cities = Lead::query()->whereNotNull('city')->where('city', '<>', '')->distinct()->orderBy('city')->pluck('city');
        $sources = Lead::query()->whereNotNull('source')->where('source', '<>', '')->distinct()->orderBy('source')->pluck('source');
        $statuses = Lead::query()->whereNotNull('status')->where('status', '<>', '')->distinct()->orderBy('status')->pluck('status');
        $activeItem = $this->activeItemFor($user);

        return view('calling-center.index', compact('campaigns', 'agents', 'folderTags', 'cities', 'sources', 'statuses', 'activeItem', 'teamView'));
    }

    public function preview(Request $request)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.create_campaign'), 403);

        $data = $request->validate($this->filterRules());
        $this->validateCampaignQuantity($request, $data);

        $leads = $this->callingCenterService
            ->leadQueryFromFilters($data)
            ->limit((int) ($data['quantity'] ?? 100))
            ->get(['id', 'name', 'phone', 'city', 'source', 'status']);

        return response()->json([
            'total' => $leads->count(),
            'leads' => $leads,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.create_campaign'), 403);

        $data = $request->validate($this->campaignRules());
        $this->validateCampaignQuantity($request, $data);
        $campaign = $this->callingCenterService->createCampaign($request->user(), $data);

        return redirect()->route('calling-center.show', $campaign)->with('success', 'Calling campaign created.');
    }

    public function show(Request $request, CallingCenterCampaign $campaign)
    {
        $this->authorizeCampaignView($request, $campaign);

        $campaign->load(['assignedTo.role', 'creator', 'items.lead', 'items.task', 'items.mcubeOutboundAttempt', 'items.callLog']);
        $stats = $this->campaignStats($campaign);

        return view('calling-center.show', compact('campaign', 'stats'));
    }

    public function start(Request $request, CallingCenterCampaign $campaign)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.create_campaign'), 403);

        $result = $this->callingCenterService->startCampaign($campaign);

        return back()->with($result['started'] ? 'success' : 'info', $result['message'] ?? 'Campaign started.');
    }

    public function pause(Request $request, CallingCenterCampaign $campaign)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.pause_own_queue') || $request->user()->canUseCallingCenter('calling_center.create_campaign'), 403);
        abort_if((int) $campaign->assigned_to !== (int) $request->user()->id && !$request->user()->canUseCallingCenter('calling_center.create_campaign'), 403);

        $this->callingCenterService->pauseCampaign($campaign);

        return back()->with('success', 'Campaign paused.');
    }

    public function cancel(Request $request, CallingCenterCampaign $campaign)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.create_campaign'), 403);
        $this->callingCenterService->cancelCampaign($campaign);

        return back()->with('success', 'Campaign cancelled.');
    }

    public function queue(Request $request)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.agent_queue'), 403);

        $activeItem = $this->activeItemFor($request->user());
        $upcomingItems = CallingCenterCampaignItem::query()
            ->with(['campaign', 'lead'])
            ->where('status', CallingCenterCampaignItem::STATUS_PENDING)
            ->whereHas('campaign', fn ($query) => $query->where('assigned_to', $request->user()->id)->whereIn('status', [CallingCenterCampaign::STATUS_RUNNING, CallingCenterCampaign::STATUS_PAUSED]))
            ->orderBy('next_call_at')
            ->limit(25)
            ->get();
        $myStats = $this->agentStats((int) $request->user()->id);

        return view('calling-center.queue', compact('activeItem', 'upcomingItems', 'myStats'));
    }

    public function submitOutcome(Request $request, CallingCenterCampaignItem $item)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.submit_outcome'), 403);

        $data = $request->validate([
            'outcome' => ['required', 'in:interested,not_interested,follow_up,cnp,junk,meeting_request,site_visit_request'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        if ($data['outcome'] === 'follow_up' && empty($data['next_action_at'])) {
            return back()->with('error', 'Follow-up date/time required.');
        }

        $result = $this->callingCenterService->submitOutcome($item, $request->user(), $data);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function process(Request $request)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.view'), 403);
        $count = $this->callingCenterService->processDueCampaigns();

        return response()->json(['success' => true, 'started' => $count]);
    }

    public function push(Request $request)
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.manual_push'), 403);

        $data = $request->validate([
            'lead_id' => ['required', 'exists:leads,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'campaign_id' => ['nullable', 'exists:calling_center_campaigns,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $request->user()->canUseCallingCenter('calling_center.approve_push') ? 'approved' : 'pending';
        CallingCenterPushRequest::create([
            'lead_id' => $data['lead_id'],
            'assigned_to' => $data['assigned_to'],
            'campaign_id' => $data['campaign_id'] ?? null,
            'requested_by' => $request->user()->id,
            'reviewed_by' => $status === 'approved' ? $request->user()->id : null,
            'reviewed_at' => $status === 'approved' ? now() : null,
            'status' => $status,
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', $status === 'approved' ? 'Call push approved.' : 'Call push request submitted for approval.');
    }

    public function export(Request $request, CallingCenterCampaign $campaign): StreamedResponse
    {
        abort_unless($request->user()->canUseCallingCenter('calling_center.export_reports'), 403);

        $campaign->load(['items.lead', 'items.callLog', 'items.mcubeOutboundAttempt', 'assignedTo']);

        return response()->streamDownload(function () use ($campaign) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Campaign', 'Agent', 'Lead ID', 'Name', 'Phone', 'Status', 'Call Status', 'Outcome', 'Attempts', 'Retry Scheduled', 'MCube Error', 'Duration', 'Recording']);
            foreach ($campaign->items as $item) {
                fputcsv($out, [
                    $campaign->name,
                    $campaign->assignedTo?->name,
                    $item->lead_id,
                    $item->lead?->name,
                    $item->phone,
                    $item->status,
                    $item->call_status,
                    $item->outcome,
                    $item->attempt_count,
                    data_get($item->meta, 'retry_scheduled') ? 'yes' : 'no',
                    $item->mcubeOutboundAttempt?->error_message,
                    $item->callLog?->duration,
                    $item->callLog?->recording_url,
                ]);
            }
            fclose($out);
        }, 'calling-center-campaign-' . $campaign->id . '.csv');
    }

    private function activeItemFor(User $user): ?CallingCenterCampaignItem
    {
        return CallingCenterCampaignItem::query()
            ->with(['campaign', 'lead', 'task', 'mcubeOutboundAttempt', 'callLog'])
            ->whereIn('status', [CallingCenterCampaignItem::STATUS_DIALING, CallingCenterCampaignItem::STATUS_OUTCOME_PENDING])
            ->whereHas('campaign', fn ($query) => $query->where('assigned_to', $user->id))
            ->latest('updated_at')
            ->first();
    }

    private function authorizeCampaignView(Request $request, CallingCenterCampaign $campaign): void
    {
        $user = $request->user();
        abort_unless($user->canUseCallingCenter('calling_center.view'), 403);
        abort_if((int) $campaign->assigned_to !== (int) $user->id && !$user->canUseCallingCenter('calling_center.view_team_report') && !$user->canUseCallingCenter('calling_center.create_campaign'), 403);
    }

    private function campaignStats(CallingCenterCampaign $campaign): array
    {
        $items = $campaign->items;
        return [
            'total' => $items->count(),
            'pending' => $items->where('status', CallingCenterCampaignItem::STATUS_PENDING)->count(),
            'dialing' => $items->where('status', CallingCenterCampaignItem::STATUS_DIALING)->count(),
            'outcome_pending' => $items->where('status', CallingCenterCampaignItem::STATUS_OUTCOME_PENDING)->count(),
            'completed' => $items->where('status', CallingCenterCampaignItem::STATUS_COMPLETED)->count(),
            'failed' => $items->where('status', CallingCenterCampaignItem::STATUS_FAILED)->count(),
            'skipped' => $items->where('status', CallingCenterCampaignItem::STATUS_SKIPPED)->count(),
            'retry_scheduled' => $items->filter(fn ($item) => (bool) data_get($item->meta, 'retry_scheduled'))->count(),
            'interested' => $items->where('outcome', 'interested')->count(),
            'follow_up' => $items->where('outcome', 'follow_up')->count(),
            'cnp' => $items->where('outcome', 'cnp')->count(),
            'not_interested' => $items->where('outcome', 'not_interested')->count(),
            'junk' => $items->where('outcome', 'junk')->count(),
        ];
    }

    private function agentStats(int $userId): array
    {
        $items = CallingCenterCampaignItem::query()
            ->whereHas('campaign', fn ($query) => $query->where('assigned_to', $userId))
            ->whereDate('updated_at', today())
            ->get();

        return [
            'today_total' => $items->count(),
            'completed' => $items->where('status', CallingCenterCampaignItem::STATUS_COMPLETED)->count(),
            'pending' => $items->where('status', CallingCenterCampaignItem::STATUS_PENDING)->count(),
            'connected' => $items->where('call_status', 'completed')->count(),
            'interested' => $items->where('outcome', 'interested')->count(),
            'follow_up' => $items->where('outcome', 'follow_up')->count(),
            'cnp' => $items->where('outcome', 'cnp')->count(),
        ];
    }

    private function filterRules(): array
    {
        return [
            'folder_type' => ['nullable', 'in:system,tag'],
            'folder_key' => ['nullable', 'in:all,unassigned'],
            'folder_tag_id' => ['nullable', 'exists:lead_tags,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    private function campaignRules(): array
    {
        return $this->filterRules() + [
            'name' => ['required', 'string', 'max:160'],
            'assigned_to' => ['required', 'exists:users,id'],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'retry_policy' => ['nullable', 'in:none,cnp_no_answer,custom'],
            'max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'lead_ids' => ['nullable', 'array'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ];
    }

    private function validateCampaignQuantity(Request $request, array $data): void
    {
        $limit = $this->campaignQuantityLimit($request->user());
        $selectedCount = count($data['lead_ids'] ?? []);
        $requested = $selectedCount > 0 ? $selectedCount : (int) ($data['quantity'] ?? 100);

        if ($requested > $limit) {
            throw ValidationException::withMessages([
                'quantity' => "Campaign limit exceeded. Your role can create up to {$limit} leads per campaign.",
            ]);
        }
    }

    private function campaignQuantityLimit(User $user): int
    {
        return $user->isAdmin() ? 1000 : 500;
    }
}
