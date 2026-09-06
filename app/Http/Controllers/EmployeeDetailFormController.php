<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\EmployeeProfileLink;
use App\Models\EmployeeTimelineEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeDetailFormController extends Controller
{
    private const DOCUMENT_FIELDS = [
        'pan_card' => 'PAN Card',
        'aadhaar_card' => 'Aadhaar Card',
        'id_proof' => 'ID Proof',
        'address_proof' => 'Address Proof',
        'appointment_letter' => 'Appointment Letter',
    ];

    public function show(string $token)
    {
        $link = $this->resolveUsableLink($token);

        if (!$link) {
            return response()->view('employee-detail-form.expired', [], 410);
        }

        $link->update(['last_opened_at' => now()]);
        $employee = $link->employeeProfile->user;
        $profile = $link->employeeProfile;
        $documents = $profile->documents->keyBy('document_type');

        return view('employee-detail-form.show', [
            'link' => $link,
            'employee' => $employee,
            'profile' => $profile,
            'documents' => $documents,
            'documentFields' => self::DOCUMENT_FIELDS,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $link = $this->resolveUsableLink($token);

        if (!$link) {
            return response()->view('employee-detail-form.expired', [], 410);
        }

        $employee = $link->employeeProfile->user;
        $profile = $link->employeeProfile;

        $rules = [
            'date_of_birth' => 'nullable|date|before:today',
            'email' => [
                $employee->email ? 'nullable' : 'required',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->id),
            ],
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'current_address' => 'nullable|string|max:3000',
            'permanent_address' => 'nullable|string|max:3000',
            'account_holder_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:100',
            'ifsc_code' => 'required|string|max:30',
            'pan_number' => 'required|string|max:30',
            'aadhaar_number' => 'required|string|max:30',
        ];

        foreach (array_keys(self::DOCUMENT_FIELDS) as $field) {
            $rules["documents.{$field}"] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $validated, $employee, $profile, $link) {
            if (!$employee->email && !empty($validated['email'])) {
                $employee->update(['email' => $validated['email']]);
            }

            $profile->update([
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'current_address' => $validated['current_address'] ?? null,
                'permanent_address' => $validated['permanent_address'] ?? null,
                'account_holder_name' => $validated['account_holder_name'],
                'bank_account_number' => $validated['bank_account_number'],
                'ifsc_code' => $validated['ifsc_code'],
                'pan_number' => $validated['pan_number'],
                'aadhaar_number' => $validated['aadhaar_number'],
            ]);

            foreach (self::DOCUMENT_FIELDS as $field => $label) {
                $file = $request->file("documents.{$field}");

                if (!$file) {
                    continue;
                }

                $existingDocument = $profile->documents()
                    ->where('document_type', $field)
                    ->latest('id')
                    ->first();

                if ($existingDocument?->file_path && Storage::disk('local')->exists($existingDocument->file_path)) {
                    Storage::disk('local')->delete($existingDocument->file_path);
                }

                $path = $file->store('employee-documents/' . $profile->employee_code, 'local');

                $documentData = [
                    'employee_profile_id' => $profile->id,
                    'document_type' => $field,
                    'document_label' => $label,
                    'document_number' => $field === 'pan_card'
                        ? ($validated['pan_number'] ?? null)
                        : ($field === 'aadhaar_card' ? ($validated['aadhaar_number'] ?? null) : null),
                    'file_path' => $path,
                    'notes' => 'Uploaded by employee self detail form.',
                    'is_required' => true,
                    'uploaded_by' => null,
                ];

                $existingDocument
                    ? $existingDocument->update($documentData)
                    : EmployeeDocument::query()->create($documentData);
            }

            $link->update([
                'status' => EmployeeProfileLink::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'last_opened_at' => now(),
            ]);

            EmployeeTimelineEvent::query()->create([
                'employee_profile_id' => $profile->id,
                'user_id' => $employee->id,
                'event_type' => 'self_detail_form_submitted',
                'title' => 'Employee details submitted via public form',
                'summary' => 'Employee updated personal, KYC, bank and document details from shared HR form.',
                'meta_json' => ['link_id' => $link->id],
                'actor_id' => null,
                'event_date' => now(),
            ]);
        });

        return redirect()
            ->route('employee-detail-form.show', $link->token)
            ->with('success', 'Details submitted. HR will review.');
    }

    private function resolveUsableLink(string $token): ?EmployeeProfileLink
    {
        $link = EmployeeProfileLink::query()
            ->with(['employeeProfile.user.role', 'employeeProfile.designation', 'employeeProfile.documents'])
            ->where('token', $token)
            ->first();

        if (!$link || !$link->isUsable()) {
            return null;
        }

        return $link;
    }
}
