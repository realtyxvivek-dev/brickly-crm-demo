@extends('layouts.app')
@section('title', 'Fraud Settings')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')
@section('content')
@php($hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Photo Check',
        'title' => 'Selfie and fraud rules',
        'subtitle' => 'Manage attendance photo validation, selfie checks, duplicate thresholds, and payroll blocking rules.',
        'stats' => [
            ['label' => 'Policies', 'value' => $policies->count(), 'note' => 'Attendance policies'],
            ['label' => 'Selfie Required', 'value' => $policies->where('selfie_required', true)->count(), 'note' => 'Enabled policies'],
            ['label' => 'Review Required', 'value' => $policies->where('face_review_required', true)->count(), 'note' => 'Manual review mode'],
        ],
    ])
    @include('admin.hr._nav')
    <div class="hr-setup-help text-sm text-[#374151]">
        <h2 class="text-lg font-semibold text-brand-primary mb-3">Quick Help</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">What This Is</div>
                <p>This page controls photo and selfie checking rules for attendance.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Simple Start</div>
                <p>Keep selfie required enabled for stronger attendance verification.</p>
                <p>You can keep manual review disabled during the pilot phase.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Tip</div>
                <p>If unsure, start with selfie required and a duplicate photo threshold only.</p>
            </div>
        </div>
    </div>
    <div class="hr-setup-card p-6 overflow-x-auto">
        <h2 class="hr-setup-section-title">Policy photo rules</h2>
        <p class="hr-setup-section-copy">Update selfie, review, and duplicate detection settings for each attendance policy.</p>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">Policy</th><th>Office</th><th>Settings</th><th>Mode</th></tr></thead>
            <tbody>
            @forelse($policies as $policy)
                <tr class="border-b last:border-0 align-top">
                    <td class="py-3">{{ $policy->name }}</td>
                    <td>{{ $policy->officeLocation?->name ?? 'Global' }}</td>
                    <td>
                        <form method="POST" action="{{ route($hrRouteBase . '.fraud-settings.update', $policy) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            @csrf
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="selfie_required" value="1" @checked($policy->selfie_required)> Selfie Required</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="face_review_required" value="1" @checked($policy->face_review_required)> Review Required</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="payroll_block_on_pending_face_review" value="1" @checked($policy->payroll_block_on_pending_face_review)> Block Payroll</label>
                            <input type="number" name="duplicate_photo_threshold" min="1" max="50" value="{{ $policy->duplicate_photo_threshold ?? 2 }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Duplicate threshold">
                            <input type="text" name="face_compare_provider" value="{{ $policy->face_compare_provider }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Face provider">
                            <input type="text" name="liveness_provider" value="{{ $policy->liveness_provider }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Liveness provider">
                            <button type="submit" class="px-4 py-2 bg-[#205A44] text-white rounded-lg w-fit">Save</button>
                        </form>
                    </td>
                    <td class="text-[#6B7280]">{{ $policy->payroll_block_on_pending_face_review ? 'Pending review blocks payroll' : 'Pending review flags only' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-4 text-[#6B7280]">No policies available.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
