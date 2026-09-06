<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeExitWorkflow;
use Illuminate\Http\Request;

class EmployeeExitWorkflowController extends Controller
{
    public function index(Request $request)
    {
        $exitCases = EmployeeExitWorkflow::query()
            ->with(['employeeProfile.user.role', 'employeeProfile.department', 'employeeProfile.designation', 'employeeProfile.assets'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.hr.exit-workflows.index', compact('exitCases'));
    }
}
