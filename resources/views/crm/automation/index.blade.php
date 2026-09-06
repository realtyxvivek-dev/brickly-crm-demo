@extends('layouts.app')

@section('title', 'Lead Automation - ' . brand_name())
@section('page-title', 'Lead Automation')
@section('page-subtitle', 'Import, assign, and manage CRM lead-distribution workflows from one workspace.')

@section('header-actions')
    <a href="{{ route('crm.automation.leads.create') }}" class="btn btn-brand-primary">
        <i class="fas fa-plus me-2"></i>Create Lead
    </a>
@endsection

@section('content')
<div class="page-shell">
    <section class="crm-hero">
        <div class="crm-hero-grid">
            <div>
                <span class="crm-kicker">
                    <i class="fas fa-bolt"></i>
                    Automation Hub
                </span>
                <h2 class="crm-hero-title">CRM automation workspace for <strong>manual creation, imports, and assignment rules</strong>.</h2>
                <p class="crm-hero-copy">
                    Existing CRM automation behavior same rahega. Is page ko admin-style control surface me reorganize kiya gaya hai
                    taaki imports, rules, aur lead operations ek hi flow me mil sakein.
                </p>
            </div>
            <div class="crm-mini-grid">
                <div class="crm-mini-card">
                    <div class="crm-mini-label">Actions</div>
                    <div class="crm-mini-value">4</div>
                    <div class="crm-mini-copy">Create leads, import leads, manage rules, and control IVR routing.</div>
                </div>
                <div class="crm-mini-card">
                    <div class="crm-mini-label">Assignment</div>
                    <div class="crm-mini-value">Live</div>
                    <div class="crm-mini-copy">Rules and imports continue using the existing CRM services and routes.</div>
                </div>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="crm-note">
            <strong>Success:</strong> {{ session('success') }}
        </div>
    @endif

    <section class="crm-grid-3">
        <article class="crm-card">
            <div class="crm-pill">Lead Intake</div>
            <h3>Create Lead</h3>
            <p class="crm-card-copy">Manually create a lead and optionally assign it immediately to a CRM user.</p>
            <div class="crm-card-divider"></div>
            <div class="crm-inline-stack">
                <a href="{{ route('crm.automation.leads.create') }}" class="btn btn-brand-primary">Open Form</a>
            </div>
        </article>

        <article class="crm-card">
            <div class="crm-pill">CSV Import</div>
            <h3>Import Leads</h3>
            <p class="crm-card-copy">Upload CSV and assign incoming leads through the existing import service and rule logic.</p>
            <div class="crm-card-divider"></div>
            <div class="crm-inline-stack">
                <a href="{{ route('crm.automation.import') }}" class="btn btn-brand-secondary">Open Import</a>
            </div>
        </article>

        <article class="crm-card">
            <div class="crm-pill">Distribution</div>
            <h3>Assignment Rules</h3>
            <p class="crm-card-copy">Manage specific-user and percentage-based lead distribution for CRM imports.</p>
            <div class="crm-card-divider"></div>
            <div class="crm-inline-stack">
                <a href="{{ route('crm.automation.rules') }}" class="btn btn-brand-secondary">View Rules</a>
            </div>
        </article>

        <article class="crm-card">
            <div class="crm-pill">SLA Automation</div>
            <h3>New Lead Response SLA</h3>
            <p class="crm-card-copy">Per-source round-robin reassignment when sales users do not record any outcome within SLA.</p>
            <div class="crm-card-divider"></div>
            <div class="crm-inline-stack">
                <a href="{{ route('crm.automation.sla.index') }}" class="btn btn-brand-secondary">Manage SLA</a>
            </div>
        </article>

        <article class="crm-card">
            <div class="crm-pill">IVR Automation</div>
            <h3>IVR Lead Distribution</h3>
            <p class="crm-card-copy">Keep MCube default receiver assignment live, but allow admin overrides for team, pool, fixed user, and fallback routing.</p>
            <div class="crm-card-divider"></div>
            <div class="crm-inline-stack">
                <a href="{{ route('crm.automation.ivr.index') }}" class="btn btn-brand-secondary">Manage IVR</a>
            </div>
        </article>
    </section>

    <section class="crm-surface">
        <div class="crm-surface-header">
            <div>
                <div class="crm-pill">CRM Import</div>
                <h3 class="crm-section-title">Lead Import</h3>
                <p class="crm-section-copy">CSV-based intake with assignment rule binding. Existing parsing and validation stay unchanged.</p>
            </div>
        </div>
        <div class="crm-grid-2" id="import-panel">
            <div class="crm-note">
                <strong>Import workflow:</strong> file upload, preview, rule selection, then lead creation through the existing CRM import service.
            </div>
            <div class="crm-inline-stack" style="justify-content:flex-end;">
                <a href="{{ route('crm.automation.import') }}" class="btn btn-brand-primary">Open Import Screen</a>
                <a href="{{ route('crm.automation.rules') }}" class="btn btn-light">Manage Rules</a>
                <a href="{{ route('crm.automation.leads.create') }}" class="btn btn-brand-secondary">Create Manually</a>
            </div>
        </div>
    </section>
</div>
@endsection
