@extends('layouts.app')
@section('title', 'Deduction Heads')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')
@section('content')
@php($hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Salary Heads',
        'title' => 'Earnings and deductions',
        'subtitle' => 'Manage the earning and deduction heads that appear on employee payslips.',
        'stats' => [
            ['label' => 'Total Heads', 'value' => $heads->count(), 'note' => 'Configured items'],
            ['label' => 'Active', 'value' => $heads->where('is_active', true)->count(), 'note' => 'Visible on salary setup'],
            ['label' => 'Deductions', 'value' => $heads->where('type', 'deduction')->count(), 'note' => 'Cutting heads'],
        ],
    ])
    @include('admin.hr._nav')
    <div class="hr-setup-help text-sm text-[#374151]">
        <h2 class="text-lg font-semibold text-brand-primary mb-3">Quick Help</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">What This Page Is</div>
                <p>Create the names used for salary additions and deductions.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Examples</div>
                <p><strong>Earning:</strong> Bonus</p>
                <p><strong>Deduction:</strong> Late Fine, Advance</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Tip</div>
                <p>Keep names simple so employees can understand them on the payslip.</p>
            </div>
        </div>
    </div>
    <div class="hr-setup-card p-6">
        <h2 class="hr-setup-section-title">Create salary head</h2>
        <p class="hr-setup-section-copy">Add a short code and readable name for an earning or deduction.</p>
        <form method="POST" action="{{ route($hrRouteBase . '.deduction-heads.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <input type="text" name="name" placeholder="Head name" class="px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            <input type="text" name="code" placeholder="Code" class="px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            <select name="type" class="px-4 py-2 border border-[#E5DED4] rounded-lg"><option value="deduction">Deduction</option><option value="earning">Earning</option></select>
            <label class="flex items-center gap-2 text-sm text-brand-primary"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button type="submit" class="md:col-span-4 px-5 py-2 bg-[#205A44] text-white rounded-lg w-fit">Save Head</button>
        </form>
    </div>
    <div class="hr-setup-card p-6 overflow-x-auto">
        <h2 class="hr-setup-section-title">Existing heads</h2>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">Name</th><th>Code</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($heads as $head)
                <tr class="border-b last:border-0"><td class="py-3">{{ $head->name }}</td><td>{{ $head->code }}</td><td>{{ ucfirst($head->type) }}</td><td>{{ $head->is_active ? 'Active' : 'Inactive' }}</td></tr>
            @empty
                <tr><td colspan="4" class="py-4 text-[#6B7280]">No deduction heads yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
