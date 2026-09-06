<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthRedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DemoQuickLoginController extends Controller
{
    private const ROLES = [
        'admin' => ['Admin', 'Full system control', 'fa-shield-halved'],
        'crm' => ['CRM Manager', 'Lead operations and allocation', 'fa-users-gear'],
        'senior_manager' => ['Senior Manager', 'Team pipeline and performance', 'fa-user-tie'],
        'sales_manager' => ['Sales Manager', 'Team leads and targets', 'fa-chart-line'],
        'assistant_sales_manager' => ['Assistant Sales Manager', 'Team execution and visits', 'fa-people-group'],
        'telecaller' => ['Telecaller', 'Calling queue and follow-ups', 'fa-headset'],
        'sales_executive' => ['Sales Executive', 'Assigned leads and site visits', 'fa-user-check'],
        'lead_quality_auditor' => ['Lead Quality Auditor', 'Insight sheet and lead audit', 'fa-magnifying-glass-chart'],
        'lead_manager' => ['Lead Manager', 'Lead bank and requests', 'fa-database'],
        'finance_manager' => ['Finance Manager', 'Revenue, expenses and approvals', 'fa-indian-rupee-sign'],
        'hr_manager' => ['HR Manager', 'People, attendance and payroll', 'fa-id-card'],
        'junior_hr' => ['Junior HR', 'Hiring and employee operations', 'fa-user-plus'],
        'ad_manager' => ['Ad Manager', 'Ad spend and lead-source quality', 'fa-rectangle-ad'],
        'marketing_manager' => ['Marketing Manager', 'Campaign and source performance', 'fa-bullhorn'],
        'marketing_executive' => ['Marketing Executive', 'Campaign execution and tasks', 'fa-pen-ruler'],
    ];

    public function index(Request $request): View
    {
        $this->ensureDemoHost($request);

        $users = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', array_keys(self::ROLES)))
            ->get()
            ->keyBy(fn (User $user) => $user->role->slug);

        return view('auth.quick-login', ['roles' => self::ROLES, 'users' => $users]);
    }

    public function login(Request $request, string $role, AuthRedirectService $redirects): RedirectResponse
    {
        $this->ensureDemoHost($request);
        abort_unless(isset(self::ROLES[$role]), 404);

        $user = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', $role))
            ->firstOrFail();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->regenerate();

        $destination = match ($role) {
            'lead_quality_auditor' => route('admin.insight-sheet.index'),
            'lead_manager' => route('lead-bank.index'),
            'telecaller' => route('telecaller.dashboard'),
            default => $redirects->redirectPathFor($user),
        };

        return redirect()->intended($destination);
    }

    private function ensureDemoHost(Request $request): void
    {
        abort_unless($request->getHost() === 'demo.bihtech.in', 404);
    }
}
