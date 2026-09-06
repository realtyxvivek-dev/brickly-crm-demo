@extends('layouts.app')
@section('title', 'Payslip Setup')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')
@section('content')
@php($hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Payslip Setup',
        'title' => 'Payslip branding and defaults',
        'subtitle' => 'Set the company name, signature, notes, and footer text used on payslip PDFs.',
        'stats' => [
            ['label' => 'Prefix', 'value' => $settings->payslip_prefix ?: '--', 'note' => 'Payslip number format'],
            ['label' => 'Company', 'value' => $settings->company_name ? 'Set' : 'Missing', 'note' => 'Payslip header'],
            ['label' => 'Signature', 'value' => $settings->signatory_image_path ? 'Set' : 'Missing', 'note' => 'PDF sign image'],
        ],
    ])
    @include('admin.hr._nav')
    <div class="hr-setup-help text-sm text-[#374151]">
        <h2 class="text-lg font-semibold text-brand-primary mb-3">Quick Help</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">What to Fill</div>
                <p>Payslip prefix, company name, notes, signatory name, signature image, and footer text.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Purpose</div>
                <p>This controls the print and PDF layout for employee payslips.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Tip</div>
                <p>Keep the company name, signatory name, and signature image accurate because they appear on the payslip PDF.</p>
            </div>
        </div>
    </div>
    <div class="hr-setup-card p-6">
        <h2 class="hr-setup-section-title">Payslip details</h2>
        <p class="hr-setup-section-copy">Update the header, notes, signature, and footer. Existing saved values stay unchanged until this form is submitted.</p>
        <form method="POST" action="{{ route($hrRouteBase . '.payslip-settings.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <input type="text" name="payslip_prefix" value="{{ $settings->payslip_prefix }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Payslip Prefix">
            <input type="text" name="company_name" value="{{ $settings->company_name }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Company Name">
            <textarea name="header_text" class="px-4 py-2 border border-[#E5DED4] rounded-lg md:col-span-2" rows="2" placeholder="Header text">{{ $settings->header_text }}</textarea>
            <textarea name="default_notes" class="px-4 py-2 border border-[#E5DED4] rounded-lg md:col-span-2" rows="3" placeholder="Default notes">{{ $settings->default_notes }}</textarea>
            <input type="text" name="signatory_name" value="{{ $settings->signatory_name }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Signatory Name">
            <input type="text" name="signatory_title" value="{{ $settings->signatory_title }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Signatory Title">
            <div class="md:col-span-2 rounded-lg border border-[#E5DED4] p-4">
                <label class="block text-sm font-semibold text-brand-primary mb-3">Signature Image</label>
                @if($settings->signatory_image_path)
                    <div class="mb-3">
                        <img src="{{ asset('storage/' . $settings->signatory_image_path) }}" alt="Current signature" class="max-h-24 bg-white border border-[#E5DED4] rounded-lg p-2">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-[#374151] mb-3">
                        <input type="checkbox" name="remove_signatory_image" value="1">
                        Remove current signature
                    </label>
                @endif
                <input type="file" name="signatory_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="block w-full text-sm text-[#374151]">
                <p class="text-xs text-[#6B7280] mt-2">PNG is preferred. A transparent background works best.</p>
            </div>
            <textarea name="footer_text" class="px-4 py-2 border border-[#E5DED4] rounded-lg md:col-span-2" rows="2" placeholder="Footer text">{{ $settings->footer_text }}</textarea>
            <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg w-fit">Save Payslip Settings</button>
        </form>
    </div>
</div>
@endsection
