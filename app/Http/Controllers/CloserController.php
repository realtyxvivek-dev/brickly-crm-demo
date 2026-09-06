<?php

namespace App\Http\Controllers;

use App\Models\SiteVisit;
use Illuminate\Http\Request;

class CloserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of closers (site visits with closer_status)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Base query for site visits with closer_status
        $query = SiteVisit::with(['lead', 'assignedTo', 'closerVerifiedBy'])
            ->whereNotNull('closer_status');

        // Role-based filtering
        if ($user->isAdmin() || $user->isCrm()) {
            // Admin and CRM see all closers
            // No additional filtering needed
        } elseif ($user->isSalesHead()) {
            // Sales Head sees all team closers
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($allTeamMemberIds)) {
                $query->whereIn('assigned_to', $allTeamMemberIds);
            } else {
                // No team members, return empty results
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isSalesManager()) {
            // Senior Manager sees team's closers
            $teamMemberIds = $user->teamMembers()->pluck('id');
            if ($teamMemberIds->isNotEmpty()) {
                $query->whereIn('assigned_to', $teamMemberIds);
            } else {
                // No team members, return empty results
                $query->whereRaw('1 = 0');
            }
        } else {
            // Sales Executive and others see only their own
            $query->where('assigned_to', $user->id);
        }

        // Filter by closer_status if provided
        if ($request->has('status') && in_array($request->status, ['draft', 'pending_crm', 'correction_required', 'approved', 'rejected'])) {
            $query->where('closer_status', $request->status);
        }

        // Filter by search term
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('project', 'like', "%{$search}%")
                  ->orWhereHas('lead', function ($leadQuery) use ($search) {
                      $leadQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Sort by converted_to_closer_at (most recent first)
        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $closers = $query->latest('converted_to_closer_at')
            ->paginate($perPage)
            ->withQueryString();

        // Get counts for status filters
        $counts = [
            'all' => (clone $query)->count(),
            'draft' => (clone $query)->where('closer_status', 'draft')->count(),
            'pending_crm' => (clone $query)->where('closer_status', 'pending_crm')->count(),
            'correction_required' => (clone $query)->where('closer_status', 'correction_required')->count(),
            'approved' => (clone $query)->where('closer_status', 'approved')->count(),
            'rejected' => (clone $query)->where('closer_status', 'rejected')->count(),
        ];

        return view('closers.index', compact('closers', 'counts'));
    }
}
