@extends('layouts.app')

@section('title', 'Forms - Admin')
@section('page-title', 'Forms')
@section('page-subtitle', 'Manage CRM forms and published field layouts')

@push('styles')
<style>
    .forms-workspace { display: grid; gap: 18px; max-width: 1500px; margin: 0 auto; }
    .forms-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding: 16px 18px; background: #fff; border: 1px solid #e2e8e4; border-radius: 10px; }
    .forms-toolbar-copy h1 { margin: 0; color: #0b2e24; font-size: 1.35rem; line-height: 1.2; }
    .forms-toolbar-copy p { margin: 4px 0 0; color: #66776f; font-size: .84rem; }
    .forms-toolbar-controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .forms-search { position: relative; min-width: min(360px, 70vw); }
    .forms-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #718078; }
    .forms-search input { width: 100%; min-height: 40px; padding: 0 12px 0 36px; border: 1px solid #d6dfda; border-radius: 8px; color: #172d24; background: #fff; font: inherit; }
    .forms-search input:focus { outline: none; border-color: #17613e; box-shadow: 0 0 0 3px rgba(23,97,62,.1); }
    .forms-filter { min-height: 40px; padding: 0 34px 0 12px; border: 1px solid #d6dfda; border-radius: 8px; color: #173427; background: #fff; font: inherit; cursor: pointer; }
    .forms-summary { display: flex; gap: 8px; flex-wrap: wrap; }
    .forms-summary-chip { display: inline-flex; align-items: center; gap: 6px; min-height: 30px; padding: 0 10px; border-radius: 999px; background: #edf6f0; color: #155337; font-size: .75rem; font-weight: 700; }
    .forms-group { display: grid; gap: 10px; }
    .forms-group-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .forms-group-title { margin: 0; color: #0b2e24; font-size: 1rem; font-weight: 800; }
    .forms-group-count { color: #718078; font-size: .78rem; }
    .forms-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .form-card { display: grid; grid-template-rows: auto auto 1fr auto; gap: 10px; min-width: 0; padding: 16px; background: #fff; border: 1px solid #e1e8e4; border-radius: 8px; transition: border-color .16s, box-shadow .16s; }
    .form-card:hover { border-color: #bfd2c7; box-shadow: 0 8px 24px rgba(12,51,36,.07); }
    .form-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .form-card-title { margin: 0; color: #102d22; font-size: .98rem; line-height: 1.35; font-weight: 800; }
    .form-status { flex: none; display: inline-flex; align-items: center; gap: 5px; min-height: 24px; padding: 0 8px; border-radius: 999px; background: #edf7f0; color: #17613e; font-size: .66rem; font-weight: 800; }
    .form-status.draft { background: #fff5dc; color: #8a5700; }
    .form-card-summary { margin: 0; color: #62736b; font-size: .8rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .form-location { display: flex; align-items: flex-start; gap: 8px; color: #385247; font-size: .78rem; line-height: 1.45; }
    .form-location i { color: #17613e; margin-top: 3px; }
    .form-tech { border-top: 1px solid #edf1ef; padding-top: 8px; color: #66776f; font-size: .74rem; }
    .form-tech summary { cursor: pointer; color: #385247; font-weight: 700; list-style: none; }
    .form-tech summary::-webkit-details-marker { display: none; }
    .form-tech summary::after { content: ' +'; color: #17613e; }
    .form-tech[open] summary::after { content: ' -'; }
    .form-tech-body { display: grid; gap: 5px; padding-top: 8px; overflow-wrap: anywhere; }
    .form-card-actions { display: grid; grid-template-columns: 44px minmax(0, 1fr); gap: 8px; padding-top: 2px; }
    .form-action-btn { min-height: 40px; border: 1px solid #d6dfda; border-radius: 8px; background: #fff; color: #174231; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 0 12px; font-size: .78rem; font-weight: 800; text-decoration: none; cursor: pointer; transition: background .15s, border-color .15s; }
    .form-action-btn:hover { background: #f3f8f5; border-color: #b9cec2; }
    .form-action-btn.primary { background: #17613e; border-color: #17613e; color: #fff; }
    .form-action-btn.primary:hover { background: #104c30; }
    .form-action-btn.danger { color: #a32424; width: 44px; padding: 0; }
    .form-custom-actions { grid-template-columns: minmax(0, 1fr) 44px; }
    .form-custom-actions form { margin: 0; }
    .empty-state { padding: 40px 18px; text-align: center; color: #718078; border: 1px dashed #cad6cf; border-radius: 8px; background: #fff; }
    .forms-no-results { display: none; padding: 36px 18px; text-align: center; color: #718078; background: #fff; border: 1px dashed #cad6cf; border-radius: 8px; }
    .form-preview-modal { display: none; position: fixed; inset: 0; z-index: 2200; padding: 18px; background: rgba(7,25,18,.58); align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .form-preview-modal.active { display: flex; }
    .modal-content-preview { width: min(1120px, 96vw); height: min(820px, 94dvh); display: grid; grid-template-rows: auto minmax(0,1fr); background: #fff; border: 1px solid #dbe5df; border-radius: 10px; overflow: hidden; box-shadow: 0 24px 70px rgba(0,0,0,.25); }
    .modal-header-preview { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #e3e9e5; }
    .preview-title-wrap { min-width: 0; }
    .preview-title-wrap h3 { margin: 0; color: #102d22; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .preview-title-wrap span { color: #718078; font-size: .72rem; }
    .preview-actions { display: flex; align-items: center; gap: 7px; }
    .preview-mode { min-height: 36px; padding: 0 10px; border: 1px solid #d6dfda; border-radius: 7px; background: #fff; color: #28493a; cursor: pointer; }
    .preview-mode.active { background: #edf6f0; border-color: #9fc2ad; color: #104c30; }
    .modal-close { width: 36px; height: 36px; border: 0; border-radius: 7px; background: #f1f5f2; color: #33483e; cursor: pointer; }
    .preview-stage { position: relative; display: flex; justify-content: center; min-height: 0; overflow: hidden; background: #edf1ef; }
    .modal-iframe { width: 100%; height: 100%; border: 0; background: #fff; transition: width .2s; }
    .modal-iframe.mobile { width: min(430px, 100%); box-shadow: 0 0 0 1px #d8e1dc; }
    .preview-loading { position: absolute; inset: 0; z-index: 2; display: grid; place-items: center; background: #f7faf8; color: #486256; font-size: .82rem; }
    .preview-loading[hidden] { display: none; }
    .preview-spinner { width: 28px; height: 28px; margin: 0 auto 10px; border: 3px solid #d8e6dd; border-top-color: #17613e; border-radius: 50%; animation: form-spin .8s linear infinite; }
    @keyframes form-spin { to { transform: rotate(360deg); } }
    @media (max-width: 1100px) { .forms-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 720px) { .forms-toolbar { align-items: stretch; padding: 14px; } .forms-toolbar-controls, .forms-search { width: 100%; min-width: 0; } .forms-filter { flex: 1; } .forms-grid { grid-template-columns: 1fr; } .form-preview-modal { padding: 6px; align-items: flex-end; } .modal-content-preview { width: 100%; height: 96dvh; border-radius: 10px 10px 0 0; } .preview-mode { display: none; } }
    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
</style>
@endpush

@section('content')
@php
    $existingForms = collect($existingForms ?? []);
    $customForms = collect($customForms ?? []);
    $categorizedForms = $existingForms->groupBy(function ($form) {
        if (($form['path'] ?? '') === 'closer.kyc') return 'KYC & Booking';
        if (str_starts_with(($form['path'] ?? ''), 'lead-detail.') && ($form['path'] ?? '') !== 'lead-detail.requirements') return 'Activity Forms';
        return 'Lead Forms';
    });
@endphp
<div class="forms-workspace">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    <div class="forms-toolbar">
        <div class="forms-toolbar-copy"><h1>Form Workspace</h1><p>Edit fields used across lead, activity and KYC workflows.</p></div>
        <div class="forms-toolbar-controls">
            <label class="forms-search" aria-label="Search forms"><i class="fas fa-search" aria-hidden="true"></i><input id="formsSearch" type="search" placeholder="Search form or location" autocomplete="off"></label>
            <select id="formsCategory" class="forms-filter" aria-label="Filter forms by category"><option value="all">All categories</option><option value="Lead Forms">Lead Forms</option><option value="Activity Forms">Activity Forms</option><option value="KYC & Booking">KYC & Booking</option><option value="Custom Forms">Custom Forms</option></select>
        </div>
    </div>
    <div class="forms-summary"><span class="forms-summary-chip"><i class="fas fa-layer-group"></i> {{ $existingForms->count() + $customForms->count() }} forms</span><span class="forms-summary-chip"><i class="fas fa-check-circle"></i> {{ $customForms->where('status', 'published')->count() }} published custom</span></div>

    @foreach(['Lead Forms', 'Activity Forms', 'KYC & Booking'] as $category)
        @php $forms = collect($categorizedForms->get($category, [])); @endphp
        @if($forms->isNotEmpty())
            <section class="forms-group" data-form-group="{{ $category }}">
                <div class="forms-group-head"><h2 class="forms-group-title">{{ $category }}</h2><span class="forms-group-count">{{ $forms->count() }} form{{ $forms->count() === 1 ? '' : 's' }}</span></div>
                <div class="forms-grid">
                    @foreach($forms as $form)
                        <article class="form-card" data-form-card data-category="{{ $category }}" data-search="{{ strtolower($form['name'].' '.$form['location'].' '.$form['path']) }}">
                            <div class="form-card-head"><h3 class="form-card-title">{{ $form['name'] }}</h3><span class="form-status"><i class="fas fa-link"></i> System</span></div>
                            <p class="form-card-summary">{{ $form['description'] ?? 'System form used by the CRM workflow.' }}</p>
                            <div><div class="form-location"><i class="fas fa-map-marker-alt"></i><span>{{ $form['location'] }}</span></div><details class="form-tech"><summary>Technical details</summary><div class="form-tech-body"><span><strong>Type:</strong> {{ ucfirst($form['type']) }}</span><span><strong>Path:</strong> {{ $form['path'] }}</span></div></details></div>
                            <div class="form-card-actions"><button type="button" class="form-action-btn" title="Preview form" aria-label="Preview {{ $form['name'] }}" data-preview-url="{{ $form['route'] }}" data-preview-name="{{ $form['name'] }}" data-preview-path="{{ $form['path'] }}" onclick="viewForm(this.dataset.previewUrl, this.dataset.previewName, this.dataset.previewPath)"><i class="fas fa-eye"></i></button><a href="{{ $form['edit_url'] }}" class="form-action-btn primary"><i class="fas fa-edit"></i> Edit form</a></div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    <section class="forms-group" data-form-group="Custom Forms">
        <div class="forms-group-head"><h2 class="forms-group-title">Custom Forms</h2><span class="forms-group-count">{{ $customForms->count() }} form{{ $customForms->count() === 1 ? '' : 's' }}</span></div>
        @if($customForms->isNotEmpty())
            <div class="forms-grid">
                @foreach($customForms as $form)
                    <article class="form-card" data-form-card data-category="Custom Forms" data-search="{{ strtolower($form->name.' '.$form->location_path.' '.$form->form_type) }}">
                        <div class="form-card-head"><h3 class="form-card-title">{{ $form->name }}</h3><span class="form-status {{ $form->status === 'published' ? '' : 'draft' }}"><i class="fas fa-{{ $form->status === 'published' ? 'check' : 'pencil-alt' }}"></i> {{ ucfirst($form->status) }}</span></div>
                        <p class="form-card-summary">{{ $form->description ?: 'Custom CRM form with '.$form->fields->count().' configured fields.' }}</p>
                        <div><div class="form-location"><i class="fas fa-map-marker-alt"></i><span>{{ $form->location_path }}</span></div><details class="form-tech"><summary>Technical details</summary><div class="form-tech-body"><span><strong>Type:</strong> {{ ucfirst($form->form_type) }}</span><span><strong>Fields:</strong> {{ $form->fields->count() }}</span><span><strong>Owner:</strong> {{ $form->creator->name ?? 'System' }}</span></div></details></div>
                        <div class="form-card-actions form-custom-actions"><a href="{{ route('admin.forms.edit', $form->id) }}" class="form-action-btn primary"><i class="fas fa-edit"></i> Edit form</a><form action="{{ route('admin.forms.destroy', $form->id) }}" method="POST" onsubmit="return confirm('Delete this form?');">@csrf @method('DELETE')<button type="submit" class="form-action-btn danger" title="Delete form" aria-label="Delete {{ $form->name }}"><i class="fas fa-trash"></i></button></form></div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No custom forms yet.</p></div>
        @endif
    </section>
    <div id="formsNoResults" class="forms-no-results"><i class="fas fa-search"></i><p>No forms match this search.</p></div>
</div>

<div id="formPreviewModal" class="form-preview-modal" role="dialog" aria-modal="true" aria-labelledby="previewModalTitle">
    <div class="modal-content-preview">
        <div class="modal-header-preview"><div class="preview-title-wrap"><h3 id="previewModalTitle">Form Preview</h3><span>Read-only live field layout</span></div><div class="preview-actions"><button type="button" class="preview-mode active" data-preview-mode="desktop" onclick="setPreviewMode('desktop')"><i class="fas fa-desktop"></i> Desktop</button><button type="button" class="preview-mode" data-preview-mode="mobile" onclick="setPreviewMode('mobile')"><i class="fas fa-mobile-alt"></i> Mobile</button><button type="button" class="modal-close" onclick="closeFormPreview()" aria-label="Close preview"><i class="fas fa-times"></i></button></div></div>
        <div class="preview-stage"><div id="previewLoading" class="preview-loading"><div><div class="preview-spinner"></div>Loading form preview...</div></div><iframe id="formPreviewIframe" class="modal-iframe" src="about:blank" title="Form preview"></iframe></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const existingPreviewBase = @js(url('admin/forms/existing-preview'));
    const previewModal = document.getElementById('formPreviewModal');
    const previewIframe = document.getElementById('formPreviewIframe');
    const previewLoading = document.getElementById('previewLoading');
    function filterForms() {
        const query = document.getElementById('formsSearch').value.trim().toLowerCase();
        const category = document.getElementById('formsCategory').value;
        let visibleCards = 0;
        document.querySelectorAll('[data-form-card]').forEach(card => { const visible = (!query || card.dataset.search.includes(query)) && (category === 'all' || card.dataset.category === category); card.hidden = !visible; if (visible) visibleCards++; });
        document.querySelectorAll('[data-form-group]').forEach(group => { const hasVisibleCard = Array.from(group.querySelectorAll('[data-form-card]')).some(card => !card.hidden); group.hidden = (category !== 'all' && group.dataset.formGroup !== category) || (!hasVisibleCard && !group.querySelector('.empty-state')); });
        document.getElementById('formsNoResults').style.display = visibleCards === 0 && query ? 'block' : 'none';
    }
    function viewForm(url, formName, formPath) { document.getElementById('previewModalTitle').textContent = formName; previewLoading.hidden = false; setPreviewMode('desktop'); previewIframe.src = formPath ? `${existingPreviewBase}/${encodeURIComponent(formPath)}` : url; previewModal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function setPreviewMode(mode) { previewIframe.classList.toggle('mobile', mode === 'mobile'); document.querySelectorAll('[data-preview-mode]').forEach(button => button.classList.toggle('active', button.dataset.previewMode === mode)); }
    function closeFormPreview() { previewModal.classList.remove('active'); previewIframe.src = 'about:blank'; document.body.style.overflow = ''; }
    previewIframe.addEventListener('load', () => { if (previewIframe.src !== 'about:blank') previewLoading.hidden = true; });
    previewModal.addEventListener('click', event => { if (event.target === previewModal) closeFormPreview(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && previewModal.classList.contains('active')) closeFormPreview(); });
    document.getElementById('formsSearch').addEventListener('input', filterForms);
    document.getElementById('formsCategory').addEventListener('change', filterForms);
</script>
@endpush
