<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentCenterController extends Controller
{
    public function index(Request $request)
    {
        $documents = EmployeeDocument::query()
            ->with(['employeeProfile.user.role', 'employeeProfile.department', 'employeeProfile.designation', 'uploader'])
            ->when($request->filled('employee_id'), fn ($query) => $query->whereHas('employeeProfile', fn ($profile) => $profile->where('user_id', $request->integer('employee_id'))))
            ->when($request->filled('document_type'), fn ($query) => $query->where('document_type', $request->input('document_type')))
            ->when($request->filled('department_id'), fn ($query) => $query->whereHas('employeeProfile', fn ($profile) => $profile->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('designation_id'), fn ($query) => $query->whereHas('employeeProfile', fn ($profile) => $profile->where('designation_id', $request->integer('designation_id'))))
            ->when($request->input('completeness') === 'missing', fn ($query) => $query->whereHas('employeeProfile', fn ($profile) => $profile))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $departments = EmployeeDepartment::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
        $designations = EmployeeDesignation::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();

        return view('admin.hr.document-center.index', compact('documents', 'departments', 'designations'));
    }

    public function download(EmployeeDocument $document)
    {
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, basename($document->file_path));
    }
}
