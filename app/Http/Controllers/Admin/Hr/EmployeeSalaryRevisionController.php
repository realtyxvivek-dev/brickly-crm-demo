<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalaryRevision;
use Illuminate\Http\Request;

class EmployeeSalaryRevisionController extends Controller
{
    public function index(Request $request)
    {
        $revisions = EmployeeSalaryRevision::query()
            ->with(['employeeProfile.user.role', 'salaryStructure', 'changedBy'])
            ->when($request->filled('employee_id'), fn ($query) => $query->whereHas('employeeProfile', fn ($profile) => $profile->where('user_id', $request->integer('employee_id'))))
            ->latest('effective_from')
            ->paginate(20)
            ->withQueryString();

        return view('admin.hr.salary-revisions.index', compact('revisions'));
    }
}
