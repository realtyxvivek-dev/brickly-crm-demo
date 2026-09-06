@extends('layouts.app')

@section('title', 'Template Management - ' . brand_name())
@section('page-title', 'Template Management')

@section('header-actions')
    <a href="{{ route('template-management.create') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-semibold">
        <i class="fas fa-plus mr-2"></i>Create Template
    </a>
@endsection

@section('content')
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
        <h2 class="text-lg font-bold text-slate-900">Meta WABA Templates</h2>
        <p class="text-sm text-slate-500 mt-1">Create, edit and submit WhatsApp templates for Meta review.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                <tr>
                    <th class="text-left px-5 py-3">Name</th>
                    <th class="text-left px-5 py-3">Category</th>
                    <th class="text-left px-5 py-3">Language</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-left px-5 py-3">Account</th>
                    <th class="text-right px-5 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($templates as $template)
                    <tr>
                        <td class="px-5 py-4 font-semibold text-slate-900">{{ $template->name ?: $template->template_id }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $template->category ?: '-' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $template->language ?: '-' }}</td>
                        <td class="px-5 py-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $template->status === 'APPROVED' ? 'bg-emerald-100 text-emerald-700' : ($template->status === 'REJECTED' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $template->status ?: 'DRAFT' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $template->metaWabaAccount?->display_phone_number ?: $template->metaWabaAccount?->name ?: '-' }}</td>
                        <td class="px-5 py-4 text-right">
                            <a class="text-emerald-700 font-bold hover:text-emerald-800" href="{{ route('template-management.edit', $template->id) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-slate-500">No templates yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-5">
        {{ $templates->links() }}
    </div>
</div>
@endsection
