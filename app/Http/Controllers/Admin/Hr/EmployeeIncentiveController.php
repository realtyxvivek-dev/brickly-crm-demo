<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeIncentiveController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'status' => 'nullable|in:pending,verified,rejected',
            'search' => 'nullable|string|max:100',
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');
        $status = $validated['status'] ?? '';
        $search = trim($validated['search'] ?? '');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $users = User::query()
            ->with([
                'role',
                'employeeProfile',
                'incentives' => function ($query) use ($start, $end, $status) {
                    $query->with([
                        'siteVisit.lead:id,name,phone',
                        'financeManagerVerifiedBy:id,name',
                        'rejectedBy:id,name',
                    ])->whereBetween('created_at', [$start, $end]);

                    if ($status === 'pending') {
                        $query->whereIn('status', ['pending_sales_head', 'pending_crm', 'pending_finance_manager']);
                    } elseif ($status !== '') {
                        $query->where('status', $status);
                    }

                    $query->latest();
                },
            ])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
                Role::TELECALLER,
            ]))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $incentives = $user->incentives;
                $pending = $incentives->whereIn('status', ['pending_sales_head', 'pending_crm', 'pending_finance_manager']);
                $approved = $incentives->where('status', 'verified');
                $rejected = $incentives->where('status', 'rejected');

                return [
                    'user' => $user,
                    'incentives' => $incentives,
                    'earned' => (float) $incentives->where('status', '!=', 'rejected')->sum('amount'),
                    'approved' => (float) $approved->sum('amount'),
                    'pending' => (float) $pending->sum('amount'),
                    'rejected' => (float) $rejected->sum('amount'),
                    'booking_count' => $incentives->where('type', 'closer')->count(),
                ];
            });

        $summary = [
            'earned' => (float) $users->sum('earned'),
            'approved' => (float) $users->sum('approved'),
            'pending' => (float) $users->sum('pending'),
            'rejected' => (float) $users->sum('rejected'),
            'bookings' => (int) $users->sum('booking_count'),
        ];

        return view('admin.hr.incentives.index', [
            'rows' => $users,
            'summary' => $summary,
            'month' => $month,
            'status' => $status,
            'search' => $search,
        ]);
    }
}
