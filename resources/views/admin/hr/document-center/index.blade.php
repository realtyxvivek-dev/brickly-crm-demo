@extends('layouts.app')

@section('title', 'Document Center')
@section('page-title', 'Document Center')

@section('content')
@php
    $hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr';
@endphp

<div class="space-y-4">
    @include('attendance._flash')
    @include('admin.hr._nav')

    <div class="bg-white rounded-2xl border border-[#E5DED4] shadow-sm">
        <div class="p-6 border-b border-[#EFE7DC] flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="text-xs font-black tracking-[0.22em] uppercase text-slate-500">HR Documents</div>
                <h1 class="text-4xl font-black tracking-[-0.06em] text-slate-900 mt-2">Document Center</h1>
                <p class="text-sm text-slate-500 mt-2">All employee KYC, appointment, and support documents in one workspace.</p>
            </div>
            <a href="{{ route($hrRouteBase . '.employees.index') }}" class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl border border-[#DCD3C6] text-slate-700 font-semibold">Open Employees</a>
        </div>

        <form method="GET" class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4 border-b border-[#EFE7DC]">
            <div>
                <label class="block text-[11px] font-black tracking-[0.18em] uppercase text-slate-500 mb-2">Document Type</label>
                <input type="text" name="document_type" value="{{ request('document_type') }}" class="w-full min-h-[44px] rounded-xl border border-[#DCD3C6] px-4" placeholder="pan_card, id_proof">
            </div>
            <div>
                <label class="block text-[11px] font-black tracking-[0.18em] uppercase text-slate-500 mb-2">Department</label>
                <select name="department_id" class="w-full min-h-[44px] rounded-xl border border-[#DCD3C6] px-4">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-black tracking-[0.18em] uppercase text-slate-500 mb-2">Designation</label>
                <select name="designation_id" class="w-full min-h-[44px] rounded-xl border border-[#DCD3C6] px-4">
                    <option value="">All designations</option>
                    @foreach($designations as $designation)
                        <option value="{{ $designation->id }}" @selected((string) request('designation_id') === (string) $designation->id)>{{ $designation->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-3 items-end">
                <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl bg-[#205A44] text-white font-bold">Apply</button>
                <a href="{{ route($hrRouteBase . '.document-center.index') }}" class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl border border-[#DCD3C6] text-slate-700 font-semibold">Clear</a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px]">
                <thead class="bg-slate-50">
                    <tr class="text-left text-[11px] uppercase tracking-[0.16em] text-slate-500">
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Document</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4">Department</th>
                        <th class="px-6 py-4">Expiry</th>
                        <th class="px-6 py-4">Uploaded By</th>
                        <th class="px-6 py-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php $profile = $document->employeeProfile; @endphp
                        <tr class="border-t border-[#EFE7DC]">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $profile?->user?->name ?? 'Unknown' }}</div>
                                <div class="text-sm text-slate-500">{{ $profile?->employee_code ?? '--' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">{{ $document->document_label }}</div>
                                <div class="text-sm text-slate-500">{{ $document->document_number ?: 'No document number' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ str_replace('_', ' ', $document->document_type) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $profile?->department?->name ?? 'Not set' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $document->expires_at?->format('d M Y') ?? 'No expiry' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $document->uploader?->name ?? 'System' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <a href="{{ route($hrRouteBase . '.employees.show', $profile?->user_id) }}#documents" class="inline-flex items-center justify-center min-h-[36px] px-3 rounded-lg border border-[#DCD3C6] text-xs font-bold text-slate-700">Employee</a>
                                    @if($document->file_path)
                                        <a href="{{ route($hrRouteBase . '.document-center.download', $document) }}" class="inline-flex items-center justify-center min-h-[36px] px-3 rounded-lg bg-[#205A44] text-xs font-bold text-white">Download</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500 font-semibold">No employee documents found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6">{{ $documents->links() }}</div>
    </div>
</div>
@endsection
