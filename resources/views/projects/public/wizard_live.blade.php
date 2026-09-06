<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Project Public Page Wizard</title>
    <style>
        :root {
            --green-900: #063a1c;
            --green-800: #0e4d2c;
            --green-700: #205a44;
            --green-100: #eef5f0;
            --sand-100: #f7f4ef;
            --sand-200: #ece5d9;
            --text-900: #163022;
            --text-700: #4e6659;
            --white: #ffffff;
            --danger: #c44949;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, sans-serif;
            background: linear-gradient(180deg, #f8f6f1 0%, #eef4ef 100%);
            color: var(--text-900);
        }
        a { color: inherit; }
        .page {
            max-width: 1380px;
            margin: 0 auto;
            padding: 24px;
        }
        .shell {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }
        .sidebar,
        .panel,
        .card {
            background: rgba(255,255,255,0.84);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(14,77,44,0.08);
            border-radius: 24px;
            box-shadow: 0 24px 50px rgba(6,58,28,0.08);
        }
        .sidebar {
            padding: 20px;
            position: sticky;
            top: 24px;
        }
        .brand {
            padding: 16px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--green-900), var(--green-700));
            color: var(--white);
            margin-bottom: 18px;
        }
        .brand strong {
            display: block;
            font-size: 18px;
            margin-bottom: 4px;
        }
        .steps {
            display: grid;
            gap: 10px;
        }
        .step-button {
            width: 100%;
            border: none;
            cursor: pointer;
            text-align: left;
            border-radius: 16px;
            padding: 14px 16px;
            background: #f6f3ee;
            color: var(--text-700);
            font-weight: 600;
        }
        .step-button.active {
            background: linear-gradient(135deg, rgba(6,58,28,0.96), rgba(32,90,68,0.94));
            color: var(--white);
        }
        .panel {
            padding: 28px;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            align-items: flex-start;
        }
        .toolbar h1 {
            margin: 0 0 4px;
            font-size: 30px;
        }
        .toolbar p {
            margin: 0;
            color: var(--text-700);
        }
        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .badge {
            padding: 10px 14px;
            border-radius: 999px;
            background: var(--green-100);
            color: var(--green-800);
            font-size: 13px;
            font-weight: 700;
        }
        .wizard-step { display: none; }
        .wizard-step.active { display: block; }
        .section-title {
            font-size: 20px;
            margin: 0 0 16px;
        }
        .section-note {
            color: var(--text-700);
            margin: 0 0 20px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        .field {
            display: grid;
            gap: 8px;
        }
        .field label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-700);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .field input,
        .field textarea,
        .field select {
            width: 100%;
            border-radius: 16px;
            border: 1px solid rgba(32,90,68,0.16);
            padding: 14px 16px;
            font: inherit;
            background: #fff;
            color: var(--text-900);
        }
        .field textarea { min-height: 110px; resize: vertical; }
        .field small { color: var(--text-700); }
        .field-action {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .field-action .btn {
            white-space: nowrap;
        }
        .field-status {
            min-height: 18px;
            font-size: 12px;
            color: var(--text-700);
        }
        .preset-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .preset-chip {
            border: 1px solid rgba(32,90,68,0.14);
            background: #f7fbf8;
            color: var(--green-800);
            padding: 10px 14px;
            border-radius: 999px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .simple-note {
            margin: 0 0 14px;
            color: var(--text-700);
            font-size: 14px;
        }
        .check-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }
        .check {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: #f7f5f1;
            border-radius: 14px;
            border: 1px solid rgba(32,90,68,0.12);
        }
        .unit-card,
        .variant-card,
        .asset-card,
        .landmark-card {
            border: 1px solid rgba(32,90,68,0.12);
            border-radius: 22px;
            padding: 18px;
            background: #fdfcf9;
        }
        .unit-card + .unit-card,
        .asset-card + .asset-card,
        .landmark-card + .landmark-card {
            margin-top: 16px;
        }
        .landmark-fetch-panel {
            border: 1px solid rgba(32,90,68,0.12);
            border-radius: 22px;
            padding: 18px;
            background: #f7fbf8;
            margin-top: 18px;
        }
        .landmark-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .landmark-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(32,90,68,0.16);
            background: #fff;
            color: var(--text-900);
            border-radius: 999px;
            padding: 10px 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .landmark-chip.active {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }
        .landmark-chip-icon {
            display: inline-flex;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            background: rgba(32,90,68,0.08);
            font-size: 12px;
        }
        .landmark-chip.active .landmark-chip-icon {
            background: rgba(255,255,255,0.18);
        }
        .landmark-fetch-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }
        .landmark-results {
            margin-top: 16px;
            display: grid;
            gap: 12px;
        }
        .landmark-result-card {
            border: 1px solid rgba(32,90,68,0.12);
            border-radius: 18px;
            background: #fff;
            padding: 14px 16px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .landmark-result-card input[type="checkbox"] {
            margin-top: 4px;
            width: 18px;
            height: 18px;
        }
        .landmark-result-body {
            display: grid;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        .landmark-result-title {
            font-weight: 800;
            color: var(--text-900);
        }
        .landmark-result-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: var(--text-700);
            font-size: 13px;
        }
        .landmark-result-address {
            color: var(--text-700);
            font-size: 13px;
            line-height: 1.5;
        }
        .landmark-result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .unit-head,
        .variant-head,
        .asset-head,
        .landmark-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .variant-list {
            display: grid;
            gap: 16px;
            margin-top: 14px;
        }
        .variant-card {
            padding: 22px;
            background: #ffffff;
            border: 1px solid rgba(32,90,68,0.10);
            box-shadow: 0 12px 28px rgba(6,58,28,0.05);
        }
        .variant-head strong {
            font-size: 20px;
            line-height: 1.1;
            color: var(--green-900);
        }
        .variant-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 248px;
            gap: 18px;
            align-items: start;
        }
        .variant-main {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .variant-side {
            display: grid;
            gap: 14px;
        }
        .variant-side-card,
        .price-preview-card {
            border: 1px solid rgba(32,90,68,0.10);
            border-radius: 18px;
            background: #f8fbf8;
            padding: 16px;
        }
        .variant-side-card label,
        .price-preview-card label {
            display: block;
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-700);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .price-preview-value {
            min-height: 68px;
            display: flex;
            align-items: center;
            font-size: 28px;
            line-height: 1;
            font-weight: 700;
            color: var(--green-900);
            letter-spacing: -0.03em;
        }
        .price-preview-value.pending {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-700);
        }
        .check-stack {
            display: grid;
            gap: 10px;
        }
        .check-stack .check {
            width: 100%;
            justify-content: flex-start;
            background: #fff;
        }
        .uploader-note {
            color: var(--text-700);
            font-size: 12px;
        }
        .pdf-meta-card {
            border: 1px solid rgba(32,90,68,0.10);
            border-radius: 16px;
            background: #f8fbf8;
            padding: 14px;
            display: grid;
            gap: 8px;
        }
        .pdf-source-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #edf3ef;
            color: var(--green-800);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            border: none;
            cursor: pointer;
            border-radius: 999px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 700;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--green-900), var(--green-700));
            color: var(--white);
        }
        .btn-secondary {
            background: #edf3ef;
            color: var(--green-800);
        }
        .btn-danger {
            background: #f8e8e8;
            color: var(--danger);
        }
        .footer-actions {
            margin-top: 26px;
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .status-bar {
            margin-bottom: 18px;
            padding: 14px 18px;
            border-radius: 18px;
            background: #f4f8f5;
            border: 1px solid rgba(32,90,68,0.1);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            color: var(--text-700);
        }
        .error-list {
            margin-bottom: 16px;
            padding: 16px 18px;
            background: #fff2f2;
            border: 1px solid #f0c6c6;
            color: #8c2f2f;
            border-radius: 18px;
        }
        .slug-preview {
            padding: 12px 14px;
            border-radius: 14px;
            background: #f3f7f4;
            color: var(--green-800);
            font-size: 14px;
        }
        .flow-hero {
            margin-bottom: 24px;
            padding: 26px;
            border: 1px solid rgba(15, 118, 84, 0.16);
            border-radius: 28px;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 22px 48px rgba(6,58,28,0.07);
        }
        .flow-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 280px;
            gap: 24px;
            align-items: start;
        }
        .flow-eyebrow {
            margin: 0;
            font-size: 12px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--green-800);
        }
        .flow-title {
            margin: 8px 0 8px;
            font-size: 30px;
            line-height: 1.12;
        }
        .flow-copy {
            margin: 0;
            max-width: 760px;
            color: var(--text-700);
        }
        .flow-steps {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }
        .flow-step {
            border: 1px solid rgba(22,48,34,0.1);
            border-radius: 18px;
            padding: 13px 14px;
            background: #f7f5f0;
            color: var(--text-700);
        }
        .flow-step.done {
            border-color: rgba(15,118,84,0.24);
            background: #eefaf2;
        }
        .flow-step.active {
            border-color: rgba(15,118,84,0.4);
            background: linear-gradient(135deg, var(--green-900), var(--green-700));
            color: var(--white);
            box-shadow: 0 18px 34px rgba(6,58,28,0.16);
        }
        .flow-step small {
            display: block;
            margin-bottom: 5px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            opacity: 0.72;
        }
        .flow-step strong {
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .flow-step span {
            display: block;
            font-size: 12px;
            line-height: 1.35;
            opacity: 0.82;
        }
        .flow-status {
            border: 1px solid rgba(22,48,34,0.1);
            border-radius: 22px;
            background: #f7f9f7;
            padding: 18px;
        }
        .flow-status-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            color: var(--text-700);
            font-size: 14px;
        }
        .flow-status-row strong {
            color: var(--text-900);
        }
        .flow-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        @media (max-width: 980px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .grid, .grid-3 { grid-template-columns: 1fr; }
            .landmark-fetch-grid { grid-template-columns: 1fr; }
            .variant-body,
            .variant-main { grid-template-columns: 1fr; }
            .flow-hero-grid { grid-template-columns: 1fr; }
            .flow-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .page { padding: 14px; }
            .flow-hero { padding: 18px; border-radius: 22px; }
            .flow-steps { grid-template-columns: 1fr; }
            .flow-title { font-size: 24px; }
        }
    </style>
</head>
<body>
@php
    $project = $project ?? null;
    $publicPage = $publicPage ?? null;
    $unitTypes = collect($unitTypes ?? []);
    $assets = collect($assets ?? []);
    $galleryAssets = $assets->where('asset_type', 'gallery_image')->values();
    $mediaAssets = $assets->where('asset_type', '!=', 'gallery_image')->values();
    $landmarks = collect($landmarks ?? []);
    $shareLink = $shareLink ?? null;
    $variantPdfMeta = $variantPdfMeta ?? [];
    $currentStep = (int) request('step', 1);
    $galleryAssetMeta = old('gallery_asset_meta', $galleryAssets->mapWithKeys(function ($asset) {
        return [
            $asset->id => [
                'title' => $asset->title,
                'category' => data_get($asset->meta, 'category', 'real'),
            ],
        ];
    })->toArray());
    $mediaTypes = [
        'brochure' => 'PDF',
        'price_sheet' => 'Price Sheet',
        'video' => 'Video URL',
        'tour_360' => '360 URL',
    ];
    $galleryCategories = [
        'real' => 'Real Image',
        'interior' => 'Interior',
        'exterior' => 'Exterior',
        'amenities' => 'Amenities',
    ];
    $landmarkFetchCategories = [
        ['key' => 'school', 'label' => 'Schools', 'icon' => 'SCH'],
        ['key' => 'hospital', 'label' => 'Hospitals', 'icon' => 'HSP'],
        ['key' => 'hotel', 'label' => 'Hotels', 'icon' => 'HOT'],
        ['key' => 'bank', 'label' => 'Banks', 'icon' => 'BNK'],
        ['key' => 'shopping_mall', 'label' => 'Malls', 'icon' => 'MAL'],
        ['key' => 'post_office', 'label' => 'Post Offices', 'icon' => 'PST'],
        ['key' => 'bus_stop', 'label' => 'Bus Stops', 'icon' => 'BUS'],
        ['key' => 'temple', 'label' => 'Temples', 'icon' => 'TMP'],
        ['key' => 'atm', 'label' => 'ATMs', 'icon' => 'ATM'],
    ];
@endphp
<div class="page">
    <div class="flow-hero">
        <div class="flow-hero-grid">
            <div>
                <p class="flow-eyebrow">Project Builder Flow</p>
                <h1 class="flow-title">{{ $project ? 'Public Page Setup' : 'Create Public Page Setup' }}</h1>
                <p class="flow-copy">Core project save ho chuka hai. Ab isi flow me hero, gallery, pricing presentation, location, downloads aur publish controls complete karo.</p>

                <div class="flow-steps">
                    <div class="flow-step done">
                        <small>Step 1</small>
                        <strong>Core Setup</strong>
                        <span>Master data saved</span>
                    </div>
                    <div class="flow-step active">
                        <small>Step 2</small>
                        <strong>Public Page</strong>
                        <span>Hero, media, pricing, CTA</span>
                    </div>
                    <div class="flow-step">
                        <small>Step 3</small>
                        <strong>Preview</strong>
                        <span>Review customer page</span>
                    </div>
                    <div class="flow-step">
                        <small>Step 4</small>
                        <strong>Publish</strong>
                        <span>Make public manually</span>
                    </div>
                </div>
            </div>

            <div class="flow-status">
                <div class="flow-status-row">
                    <span>Public status</span>
                    <strong>{{ ucfirst($publicPage->status ?? 'draft') }}</strong>
                </div>
                @if($project)
                    <div class="flow-status-row">
                        <span>Project ID</span>
                        <strong>#{{ $project->id }}</strong>
                    </div>
                @endif
                <div class="flow-status-row">
                    <span>Live trigger</span>
                    <strong>Publish Public Page</strong>
                </div>
                <div class="flow-actions">
                    @if($project)
                        <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary">Edit Core Setup</a>
                        <a href="{{ route('projects.public-pages.preview', $project) }}" class="btn btn-secondary">Preview</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <strong>Step 2: Public Page</strong>
                <span>Customer-facing share page layout and publish control</span>
            </div>
            <div class="steps">
                @foreach ([1 => 'Core Project Data', 2 => 'Hero & Identity', 3 => 'Quick Facts & Pricing', 4 => 'Gallery, Media & Downloads', 5 => 'Location & Travel', 6 => 'Share, Preview & Publish'] as $index => $label)
                    <button type="button" class="step-button {{ $currentStep === $index ? 'active' : '' }}" data-step-nav="{{ $index }}">{{ $index }}. {{ $label }}</button>
                @endforeach
            </div>
        </aside>

        <main class="panel">
            <div class="toolbar">
                <div>
                    <h1>{{ $project ? 'Public Page Builder' : 'Create Public Page' }}</h1>
                    <p>Core project data alag rahega. Yahan se hero, gallery, pricing presentation, location story, CTA aur public publish state control hoga.</p>
                </div>
                <div class="badge-row">
                    <span class="badge">Status: {{ ucfirst($publicPage->status ?? 'draft') }}</span>
                    @if($publicPage?->last_saved_at)
                        <span class="badge">Saved {{ $publicPage->last_saved_at->diffForHumans() }}</span>
                    @endif
                    @if($project)
                        <span class="badge">Project ID: {{ $project->id }}</span>
                    @endif
                    @if($publicPage?->published_at)
                        <span class="badge">Last published {{ $publicPage->published_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>

            @if(session('status'))
                <div class="status-bar">
                    <strong>{{ session('status') }}</strong>
                    @if($publicPage?->last_saved_at)
                        <span>Last saved by user #{{ $publicPage->last_saved_by }} at {{ $publicPage->last_saved_at->format('d M Y, h:i A') }}</span>
                    @endif
                </div>
            @endif

            @if($project && (
                blank($project->builder_id)
                || blank($project->project_name)
                || blank($project->city)
                || blank($project->project_status)
                || blank($project->possession_date)
            ))
                <div class="status-bar" style="background:#fff7ed; border-color:#fdba74; color:#9a3412;">
                    <strong>Core setup incomplete</strong>
                    <span>Builder, project name, city, project status, aur possession date complete kar do taaki public page fallback values clean rahen.</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="error-list">
                    <strong>Fix these before continuing:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('publish_warnings'))
                <div class="error-list" style="background:#fff7ed;border-color:#fdba74;color:#9a3412;">
                    <strong>Warnings before publish:</strong>
                    <p style="margin:8px 0 12px;">Page abhi bhi public ki ja sakti hai. Missing cheezein baad me fix kar sakte ho.</p>
                    <ul>
                        @foreach (collect(session('publish_warnings'))->flatten() as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="public-page-form" method="POST" action="{{ route('projects.public-pages.save') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project?->id }}">
                <input type="hidden" name="current_step" id="current_step" value="{{ $currentStep }}">
                <input type="hidden" name="next_step" id="next_step" value="{{ $currentStep }}">
                <input type="hidden" name="publish_after_save" id="publish_after_save" value="0">
                <input type="hidden" name="force_publish" id="force_publish" value="0">

                <section class="wizard-step {{ $currentStep === 1 ? 'active' : '' }}" data-step="1">
                    <h2 class="section-title">Core Project Data</h2>
                    <p class="section-note">Yahan sirf project master identity rakho. Public page ka presentation content next steps me control hoga.</p>
                    <div class="card" style="padding:18px; margin-bottom:18px;">
                        <div class="grid">
                            <div>
                                <strong style="display:block; margin-bottom:8px;">Project master me kya rehna chahiye</strong>
                                <p class="simple-note" style="margin-bottom:0;">Builder, project name, city, locality, status, possession, RERA, short overview, and base highlights.</p>
                            </div>
                            <div>
                                <strong style="display:block; margin-bottom:8px;">Public page builder kya control karega</strong>
                                <p class="simple-note" style="margin-bottom:0;">Hero copy, badges, branding, gallery, public pricing view, location story, downloads, and customer CTA layout.</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid">
                        <div class="field">
                            <label>Builder</label>
                            <select name="builder_id" required>
                                <option value="">Select builder</option>
                                @foreach($builders as $builder)
                                    <option value="{{ $builder->id }}" @selected(old('builder_id', $project?->builder_id) == $builder->id)>{{ $builder->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Project Name</label>
                            <input type="text" name="name" value="{{ old('name', $project?->name) }}" required>
                        </div>
                        <div class="field">
                            <label>City</label>
                            <input type="text" name="city" value="{{ old('city', $project?->city) }}" required>
                        </div>
                        <div class="field">
                            <label>Locality</label>
                            <input type="text" name="area" value="{{ old('area', $project?->area) }}" required>
                        </div>
                        <div class="field">
                            <label>Project Status</label>
                            <select name="project_status">
                                @foreach(['prelaunch' => 'Prelaunch', 'under_construction' => 'Under Construction', 'ready' => 'Ready'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('project_status', $project?->project_status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Possession Date</label>
                            <input type="date" name="possession_date" value="{{ old('possession_date', optional($project?->possession_date)->format('Y-m-d')) }}">
                        </div>
                        <div class="field">
                            <label>RERA No.</label>
                            <input type="text" name="rera_no" value="{{ old('rera_no', $project?->rera_no) }}">
                        </div>
                        <div class="field">
                            <label>Project Active</label>
                            <div class="check-row">
                                <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $project?->is_active ?? true))> Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="field" style="margin-top:18px;">
                        <label>Slug Preview</label>
                        <div class="slug-preview" id="slug-preview">/share/project/{{ $shareLink?->token ?? 'generated-after-save' }}</div>
                    </div>
                    <div class="field" style="margin-top:18px;">
                        <label>Short Overview</label>
                        <textarea name="short_overview">{{ old('short_overview', $project?->short_overview) }}</textarea>
                    </div>
                    <div class="field" style="margin-top:18px;">
                        <label>Project Highlights</label>
                        <textarea name="project_highlights">{{ old('project_highlights', is_array($project?->project_highlights) ? implode("\n", $project->project_highlights) : $project?->project_highlights) }}</textarea>
                    </div>
                </section>

                <section class="wizard-step {{ $currentStep === 2 ? 'active' : '' }}" data-step="2">
                    <h2 class="section-title">Hero & Brand</h2>
                    <p class="section-note">Yeh customer ka first impression section hai. Headline, badges, cover, aur builder branding yahin set hogi.</p>
                    <div class="grid">
                        <div class="field">
                            <label>Hero Title</label>
                            <input type="text" name="hero_title" value="{{ old('hero_title', $publicPage?->hero_title) }}">
                        </div>
                        <div class="field">
                            <label>Hero Subtitle</label>
                            <input type="text" name="hero_subtitle" value="{{ old('hero_subtitle', $publicPage?->hero_subtitle) }}">
                        </div>
                    </div>
                    <div class="field" style="margin-top:18px;">
                        <label>Short Intro</label>
                        <textarea name="short_intro">{{ old('short_intro', $publicPage?->short_intro) }}</textarea>
                    </div>
                    <div class="field" style="margin-top:18px;">
                        <label>Featured Badges</label>
                        <textarea name="featured_badges" placeholder="Ready to move&#10;Near expressway&#10;Club + pool">{{ old('featured_badges', $publicPage?->featured_badges ? implode("\n", $publicPage->featured_badges) : '') }}</textarea>
                    </div>
                    <div class="grid" style="margin-top:18px;">
                        <div class="field">
                            <label>Hero Image</label>
                            <input type="file" name="hero_cover_image" accept="image/*">
                            @if($publicPage?->hero_cover_url)
                                <small>Current: <a href="{{ $publicPage->hero_cover_url }}" target="_blank">View hero image</a></small>
                            @endif
                            <small class="uploader-note">If you do not upload one, the default header image will be used automatically.</small>
                        </div>
                        <div class="field">
                            <label>Builder Logo</label>
                            <input type="file" name="builder_logo_image" accept="image/*">
                            @if($publicPage?->builder_logo_url)
                                <small>Current: <a href="{{ $publicPage->builder_logo_url }}" target="_blank">View builder logo</a></small>
                            @endif
                        </div>
                    </div>
                    <div class="field" style="margin-top:18px;">
                        <label>Gallery Images</label>
                        <input type="file" name="gallery_images[]" accept="image/*" multiple>
                        <small>Upload multiple images for the public gallery. New uploads will appear as Real Image by default.</small>
                    </div>
                    @if($galleryAssets->isNotEmpty())
                        <div class="card" style="margin-top:18px; padding:18px;">
                            <h3 class="section-title" style="margin-bottom:10px;">Current Gallery Images</h3>
                            <p class="section-note" style="margin-bottom:14px;">Tag each image so it shows in the correct public image filter.</p>
                            <div class="grid">
                                @foreach($galleryAssets as $galleryAsset)
                                    <div class="asset-card">
                                        <div class="asset-head">
                                            <strong>Gallery Image {{ $loop->iteration }}</strong>
                                        </div>
                                        <img src="{{ $galleryAsset->file_url }}" alt="{{ data_get($galleryAssetMeta, $galleryAsset->id . '.title', $galleryAsset->title) }}" style="width:100%; height:180px; object-fit:cover; border-radius:16px; margin-bottom:14px;">
                                        <div class="grid">
                                            <div class="field">
                                                <label>Image Tag</label>
                                                <select name="gallery_asset_meta[{{ $galleryAsset->id }}][category]">
                                                    @foreach($galleryCategories as $value => $label)
                                                        <option value="{{ $value }}" @selected(data_get($galleryAssetMeta, $galleryAsset->id . '.category', 'real') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label>Image Title</label>
                                                <input type="text" name="gallery_asset_meta[{{ $galleryAsset->id }}][title]" value="{{ data_get($galleryAssetMeta, $galleryAsset->id . '.title', $galleryAsset->title) }}" placeholder="Living room view">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <section class="wizard-step {{ $currentStep === 3 ? 'active' : '' }}" data-step="3">
                    <h2 class="section-title">Pricing & Floor Plans</h2>
                    <p class="section-note">Public page par pricing strip, configuration summary, aur floor-plan presentation isi step se aayegi.</p>
                    <div class="grid-3">
                        <div class="field">
                            <label>Company Rate / Sq.ft. *</label>
                            <input type="number" name="base_rate_per_sqft" id="default_rate" step="0.01" required value="{{ old('base_rate_per_sqft', $publicPage?->base_rate_per_sqft) }}">
                        </div>
                        <div class="field">
                            <label>Rounding Rule</label>
                            <select name="rounding_rule" id="default_rounding">
                                @foreach(['none' => 'No rounding', 'nearest_1000' => 'Nearest 1,000', 'nearest_10000' => 'Nearest 10,000', 'nearest_100000' => 'Nearest 1 Lakh'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('rounding_rule', $publicPage?->rounding_rule ?? 'none') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Rule</label>
                            <div class="slug-preview">Hero "starting from" will use the lowest visible available/hold variant price.</div>
                        </div>
                    </div>
                    @if($project)
                        <div class="actions" style="margin-top:18px;">
                            <button type="button" class="btn btn-secondary js-post-action" data-url="{{ route('projects.public-pages.variant-pdf.regenerate-all', $project) }}">Regenerate All Auto PDFs</button>
                        </div>
                    @endif

                    <div id="unit-types-root" style="margin-top:18px;">
                        @forelse(old('unit_types', $unitTypes->map(fn($unitType) => [
                            'id' => $unitType->id,
                            'name' => $unitType->name,
                            'display_order' => $unitType->display_order,
                            'is_primary' => $unitType->is_primary,
                            'variants' => $unitType->sizeVariants->map(fn($variant) => [
                                'id' => $variant->id,
                                'size_label' => $variant->size_label,
                                'builtup_area_sqft' => $variant->builtup_area_sqft,
                                'carpet_area_sqft' => $variant->carpet_area_sqft,
                                'rounding_rule' => $variant->rounding_rule,
                                'manual_price_override' => $variant->manual_price_override,
                                'use_manual_price' => filled($variant->manual_price_override),
                                'status' => $variant->status,
                                'visible_on_public_page' => $variant->visible_on_public_page,
                                'is_price_on_request' => $variant->is_price_on_request,
                                'is_featured' => $variant->is_featured,
                                'display_order' => $variant->display_order,
                                'details_pdf_path' => $variant->details_pdf_path,
                                'floor_plan_image_path' => $variant->floor_plan_image_path,
                            ])->toArray(),
                        ])->toArray()) as $unitIndex => $unitType)
                            <div class="unit-card" data-unit-index="{{ $unitIndex }}">
                                <div class="unit-head">
                                    <strong>Unit Type {{ $unitIndex + 1 }}</strong>
                                    <button type="button" class="btn btn-danger remove-unit">Remove Unit</button>
                                </div>
                                <div class="grid">
                                    <div class="field">
                                        <label>Unit Type Name</label>
                                        <input type="hidden" name="unit_types[{{ $unitIndex }}][id]" value="{{ data_get($unitType, 'id') }}">
                                        <input type="text" name="unit_types[{{ $unitIndex }}][name]" value="{{ data_get($unitType, 'name') }}" placeholder="3 BHK">
                                    </div>
                                    <div class="field">
                                        <label>Primary Unit Type</label>
                                        <div class="check-row">
                                            <label class="check"><input type="checkbox" name="unit_types[{{ $unitIndex }}][is_primary]" value="1" @checked(data_get($unitType, 'is_primary'))> Mark as featured unit type</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="variant-list">
                                    @foreach(data_get($unitType, 'variants', []) as $variantIndex => $variant)
                                        <div class="variant-card" data-variant-index="{{ $variantIndex }}">
                                            <div class="variant-head">
                                                <strong>Variant {{ $variantIndex + 1 }}</strong>
                                                <div class="actions">
                                                    <button type="button" class="btn btn-secondary clone-variant">Clone</button>
                                                    <button type="button" class="btn btn-danger remove-variant">Remove</button>
                                                </div>
                                            </div>
                                            <div class="variant-body">
                                                <div class="variant-main">
                                                    <div class="field">
                                                        <label>Size Label</label>
                                                        <input type="hidden" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][id]" value="{{ data_get($variant, 'id') }}">
                                                        <input type="text" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][size_label]" value="{{ data_get($variant, 'size_label') }}" placeholder="1450 Sq.ft.">
                                                    </div>
                                                    <div class="field">
                                                        <label>Built-up Area</label>
                                                        <input type="number" step="0.01" class="variant-builtup" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][builtup_area_sqft]" value="{{ data_get($variant, 'builtup_area_sqft') }}">
                                                    </div>
                                                    <div class="field">
                                                        <label>Carpet Area</label>
                                                        <input type="number" step="0.01" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][carpet_area_sqft]" value="{{ data_get($variant, 'carpet_area_sqft') }}">
                                                    </div>
                                                    <div class="field">
                                                        <label>Status</label>
                                                        <select name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][status]">
                                                            @foreach(['available' => 'Available', 'hold' => 'Hold', 'sold_out' => 'Sold Out', 'hidden' => 'Hidden'] as $value => $label)
                                                                <option value="{{ $value }}" @selected(data_get($variant, 'status', 'available') === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label>Variant Rounding</label>
                                                        <select class="variant-rounding" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][rounding_rule]">
                                                            @foreach(['' => 'Use default', 'none' => 'No rounding', 'nearest_1000' => 'Nearest 1,000', 'nearest_10000' => 'Nearest 10,000', 'nearest_100000' => 'Nearest 1 Lakh'] as $value => $label)
                                                                <option value="{{ $value }}" @selected((string) data_get($variant, 'rounding_rule') === (string) $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label>Manual Price</label>
                                                        <div class="check-row" style="margin-bottom:10px;">
                                                            <label class="check"><input type="checkbox" class="variant-manual-toggle" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][use_manual_price]" value="1" @checked(data_get($variant, 'use_manual_price'))> Set manual price</label>
                                                        </div>
                                                        <input type="number" step="0.01" class="variant-manual" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][manual_price_override]" value="{{ data_get($variant, 'manual_price_override') }}" @disabled(!data_get($variant, 'use_manual_price'))>
                                                    </div>
                                                    <div class="field">
                                                        <label>Floor Plan Image</label>
                                                        <input type="file" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][floor_plan_image]" accept="image/*">
                                                        @if(data_get($variant, 'floor_plan_image_path'))
                                                            <small class="uploader-note">Existing floor plan attached</small>
                                                        @endif
                                                        <small class="uploader-note">Missing upload will use the default floor-plan image automatically.</small>
                                                    </div>
                                                    <div class="field">
                                                        <label>Details PDF</label>
                                                        <input type="file" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][details_pdf]" accept="application/pdf">
                                                        @php
                                                            $pdfMeta = data_get($variantPdfMeta, data_get($variant, 'id'), []);
                                                        @endphp
                                                        <div class="pdf-meta-card">
                                                            <span class="pdf-source-badge">
                                                                @if(data_get($pdfMeta, 'source') === 'uploaded')
                                                                    Uploaded PDF
                                                                @elseif(data_get($pdfMeta, 'source') === 'generated')
                                                                    Auto-generated PDF
                                                                @elseif(data_get($pdfMeta, 'source') === 'auto_available')
                                                                    Auto PDF Available
                                                                @else
                                                                    PDF Missing
                                                                @endif
                                                            </span>
                                                            @if(data_get($pdfMeta, 'generated_at'))
                                                                <small class="uploader-note">Generated {{ \Illuminate\Support\Carbon::parse(data_get($pdfMeta, 'generated_at'))->diffForHumans() }}</small>
                                                            @endif
                                                            <div class="actions">
                                                                @if($project && data_get($variant, 'id'))
                                                                    <a class="btn btn-secondary" href="{{ route('projects.public-pages.variant-pdf', [$project, data_get($variant, 'id')]) }}" target="_blank" rel="noopener">Preview PDF</a>
                                                                    <button type="button" class="btn btn-secondary js-post-action" data-url="{{ route('projects.public-pages.variant-pdf.regenerate', [$project, data_get($variant, 'id')]) }}">Regenerate Auto PDF</button>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <small class="uploader-note">If manual PDF is missing, system-generated details PDF will be used when enough variant data exists.</small>
                                                    </div>
                                                </div>
                                                <div class="variant-side">
                                                    <div class="price-preview-card">
                                                        <label>Auto Price Preview</label>
                                                        <div class="variant-price-preview price-preview-value pending">Price pending</div>
                                                    </div>
                                                    <div class="variant-side-card">
                                                        <label>Visibility & Tags</label>
                                                        <div class="check-stack">
                                                            <label class="check"><input type="checkbox" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][visible_on_public_page]" value="1" @checked(data_get($variant, 'visible_on_public_page', true))> Visible</label>
                                                            <label class="check"><input type="checkbox" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][is_price_on_request]" value="1" @checked(data_get($variant, 'is_price_on_request'))> Price on request</label>
                                                            <label class="check"><input type="checkbox" name="unit_types[{{ $unitIndex }}][variants][{{ $variantIndex }}][is_featured]" value="1" @checked(data_get($variant, 'is_featured'))> Featured</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="actions" style="margin-top:16px;">
                                    <button type="button" class="btn btn-secondary add-variant">Add Variant</button>
                                </div>
                            </div>
                        @empty
                            <div class="unit-card" data-unit-index="0">
                                <div class="unit-head">
                                    <strong>Unit Type 1</strong>
                                    <button type="button" class="btn btn-danger remove-unit">Remove Unit</button>
                                </div>
                                <div class="grid">
                                    <div class="field">
                                        <label>Unit Type Name</label>
                                        <input type="text" name="unit_types[0][name]" placeholder="3 BHK">
                                    </div>
                                    <div class="field">
                                        <label>Primary Unit Type</label>
                                        <div class="check-row">
                                            <label class="check"><input type="checkbox" name="unit_types[0][is_primary]" value="1"> Mark as featured unit type</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="variant-list"></div>
                                <div class="actions" style="margin-top:16px;">
                                    <button type="button" class="btn btn-secondary add-variant">Add Variant</button>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    <div class="actions" style="margin-top:16px;">
                        <button type="button" class="btn btn-primary" id="add-unit-type">Add Unit Type</button>
                    </div>

                    @php
                        $otherCharges = old('other_charges', $publicPage?->other_charges ?? []);
                    @endphp
                    <div class="card" style="margin-top:20px; padding:18px;">
                        <h3 class="section-title" style="margin-bottom:10px;">Other Charges</h3>
                        <p class="simple-note">Show customers the additional charges applicable on top of base pricing.</p>
                        <div class="preset-row">
                            @foreach(['Parking', 'Club Membership', 'IFMS', 'GST', 'Floor Rise', 'Registry', 'Power Backup'] as $presetCharge)
                                <button type="button" class="preset-chip add-charge-preset" data-charge-name="{{ $presetCharge }}">{{ $presetCharge }}</button>
                            @endforeach
                        </div>
                        <div id="other-charges-root">
                            @forelse($otherCharges as $chargeIndex => $charge)
                                <div class="asset-card other-charge-card" data-charge-index="{{ $chargeIndex }}">
                                    <div class="asset-head">
                                        <strong>Charge {{ $chargeIndex + 1 }}</strong>
                                        <button type="button" class="btn btn-danger remove-charge">Remove</button>
                                    </div>
                                    <div class="grid-3">
                                        <div class="field">
                                            <label>Charge Name</label>
                                            <input type="text" name="other_charges[{{ $chargeIndex }}][name]" value="{{ data_get($charge, 'name') }}" placeholder="Parking">
                                        </div>
                                        <div class="field">
                                            <label>Value</label>
                                            <input type="text" class="other-charge-value" name="other_charges[{{ $chargeIndex }}][value]" value="{{ data_get($charge, 'value') }}" placeholder="300000" @disabled(data_get($charge, 'type') === 'on_request')>
                                        </div>
                                        <div class="field">
                                            <label>Type</label>
                                            <select class="other-charge-type" name="other_charges[{{ $chargeIndex }}][type]">
                                                @foreach(['fixed' => 'Fixed', 'per_sqft' => 'Per Sq.ft.', 'percent' => 'Percent', 'on_request' => 'On Request'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(data_get($charge, 'type') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="asset-card other-charge-card" data-charge-index="0">
                                    <div class="asset-head">
                                        <strong>Charge 1</strong>
                                        <button type="button" class="btn btn-danger remove-charge">Remove</button>
                                    </div>
                                    <div class="grid-3">
                                        <div class="field">
                                            <label>Charge Name</label>
                                            <input type="text" name="other_charges[0][name]" placeholder="Parking">
                                        </div>
                                        <div class="field">
                                            <label>Value</label>
                                            <input type="text" class="other-charge-value" name="other_charges[0][value]" placeholder="300000">
                                        </div>
                                        <div class="field">
                                            <label>Type</label>
                                            <select class="other-charge-type" name="other_charges[0][type]">
                                                <option value="fixed">Fixed</option>
                                                <option value="per_sqft">Per Sq.ft.</option>
                                                <option value="percent">Percent</option>
                                                <option value="on_request">On Request</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                        <div class="actions" style="margin-top:14px;">
                            <button type="button" class="btn btn-secondary" id="add-other-charge">Add Charge</button>
                        </div>
                    </div>
                </section>

                <section class="wizard-step {{ $currentStep === 4 ? 'active' : '' }}" data-step="4">
                    <h2 class="section-title">Gallery, Media & Downloads</h2>
                    <p class="section-note">Customer-facing visuals aur downloadable assets ko category aur order ke saath yahin manage karo. Variant PDFs units step me hi rahenge.</p>
                    <div id="assets-root">
                        @forelse(old('assets', $mediaAssets->map(fn($asset) => [
                            'id' => $asset->id,
                            'asset_type' => $asset->asset_type,
                            'title' => $asset->title,
                            'external_url' => $asset->external_url,
                            'tracking_key' => $asset->tracking_key,
                            'source_type' => $asset->source_type,
                            'preview_image_path' => $asset->preview_image_path,
                            'file_path' => $asset->file_path,
                            'is_featured' => $asset->is_featured,
                            'meta' => $asset->meta ?? [],
                        ])->toArray()) as $assetIndex => $asset)
                            <div class="asset-card" data-asset-index="{{ $assetIndex }}">
                                <div class="asset-head">
                                    <strong>Asset {{ $assetIndex + 1 }}</strong>
                                    <button type="button" class="btn btn-danger remove-asset">Remove</button>
                                </div>
                                <div class="grid">
                                    <div class="field">
                                        <label>Asset Type</label>
                                        <input type="hidden" name="assets[{{ $assetIndex }}][id]" value="{{ data_get($asset, 'id') }}">
                                        <select name="assets[{{ $assetIndex }}][asset_type]">
                                            @foreach($mediaTypes as $value => $label)
                                                <option value="{{ $value }}" @selected(data_get($asset, 'asset_type') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Title</label>
                                        <input type="text" name="assets[{{ $assetIndex }}][title]" value="{{ data_get($asset, 'title') }}">
                                    </div>
                                    <div class="field">
                                        <label>External URL</label>
                                        <input type="url" name="assets[{{ $assetIndex }}][external_url]" value="{{ data_get($asset, 'external_url') }}">
                                    </div>
                                    <div class="field">
                                        <label>Tracking Key</label>
                                        <input type="text" name="assets[{{ $assetIndex }}][tracking_key]" value="{{ data_get($asset, 'tracking_key') }}">
                                    </div>
                                    <div class="field">
                                        <label>Media Tag</label>
                                        <select name="assets[{{ $assetIndex }}][meta][category]">
                                            <option value="">General</option>
                                            <option value="walkthrough" @selected(data_get($asset, 'meta.category') === 'walkthrough')>Walkthrough</option>
                                            <option value="site" @selected(data_get($asset, 'meta.category') === 'site')>Site Update</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>File Upload</label>
                                        <input type="file" name="assets[{{ $assetIndex }}][file]">
                                        @if(data_get($asset, 'file_path'))
                                            <small>Existing file attached</small>
                                        @endif
                                    </div>
                                    <div class="field">
                                        <label>Preview Image</label>
                                        <input type="file" name="assets[{{ $assetIndex }}][preview_image]" accept="image/*">
                                        @if(data_get($asset, 'preview_image_path'))
                                            <small>Existing preview image attached</small>
                                        @endif
                                    </div>
                                    <div class="field">
                                        <label>Featured Placement</label>
                                        <div class="check-row">
                                            <label class="check">
                                                <input type="checkbox" name="assets[{{ $assetIndex }}][is_featured]" value="1" @checked(data_get($asset, 'is_featured'))>
                                                Feature above Unit Plans
                                            </label>
                                        </div>
                                        <small>Only one video should be featured here. If multiple are checked, latest saved one will win.</small>
                                    </div>
                                </div>
                            </div>
                        @empty
                        @endforelse
                    </div>
                    <div class="actions" style="margin-top:16px;">
                        <button type="button" class="btn btn-primary" id="add-asset">Add Media Asset</button>
                    </div>
                </section>

                <section class="wizard-step {{ $currentStep === 5 ? 'active' : '' }}" data-step="5">
                    <h2 class="section-title">Location & Travel Cues</h2>
                    <p class="section-note">Map, location summary, nearby landmarks, and travel-origin suggestions public page ke liye yahin control honge.</p>
                    <div class="grid">
                        <div class="field">
                            <label>Location Summary</label>
                            <textarea name="location_summary">{{ old('location_summary', $publicPage?->location_summary) }}</textarea>
                        </div>
                        <div class="field">
                            <label>Map Embed / Link</label>
                            <textarea name="map_embed">{{ old('map_embed', $publicPage?->map_embed) }}</textarea>
                        </div>
                    </div>
                    <div class="field" style="margin-top:16px;">
                        <label>Google Maps URL</label>
                        <div class="field-action">
                            <input type="url" data-map-url placeholder="Paste Google Maps share URL for project location">
                            <button type="button" class="btn btn-secondary js-fill-coordinates" data-target="project">Auto Fill Lat / Lng</button>
                        </div>
                        <div class="field-status" data-map-status="project"></div>
                    </div>
                    <div class="grid-3" style="margin-top:16px;">
                        <div class="field">
                            <label>Latitude</label>
                            <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $publicPage?->latitude) }}" placeholder="26.8467" data-lat-target="project">
                        </div>
                        <div class="field">
                            <label>Longitude</label>
                            <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $publicPage?->longitude) }}" placeholder="80.9462" data-lng-target="project">
                        </div>
                        <div class="field">
                            <label>Map Zoom</label>
                            <input type="number" min="1" max="20" name="map_zoom" value="{{ old('map_zoom', $publicPage?->map_zoom) }}" placeholder="13">
                        </div>
                    </div>
                    @php
                        $travelPreview = $travelTimePreview ?? ['origin_source' => 'search', 'popular_origins' => []];
                        $sourceLabel = match($travelPreview['origin_source'] ?? 'search') {
                            'project' => 'Project-specific origins active',
                            'city' => 'City defaults active',
                            default => 'Search-only fallback',
                        };
                    @endphp
                    <div class="section-note" style="margin-top:14px;">
                        Travel widget source preview: <strong>{{ $sourceLabel }}</strong>
                        @if(!empty($travelPreview['popular_origins']))
                            · {{ count($travelPreview['popular_origins']) }} quick origin{{ count($travelPreview['popular_origins']) === 1 ? '' : 's' }} ready
                        @endif
                    </div>
                    <div id="popular-origins-root" style="margin-top:18px;">
                        @forelse(old('popular_origins', $publicPage?->popular_origins ?? []) as $originIndex => $origin)
                            <div class="landmark-card" data-origin-index="{{ $originIndex }}">
                                <div class="landmark-head">
                                    <strong>Popular Origin {{ $originIndex + 1 }}</strong>
                                    <button type="button" class="btn btn-danger remove-origin">Remove</button>
                                </div>
                                <div class="field" style="margin-bottom:14px;">
                                    <label>Google Maps URL</label>
                                    <div class="field-action">
                                        <input type="url" data-map-url placeholder="Paste Google Maps share URL for this origin">
                                        <button type="button" class="btn btn-secondary js-fill-coordinates" data-target="origin">Auto Fill Lat / Lng</button>
                                    </div>
                                    <div class="field-status" data-map-status="origin"></div>
                                </div>
                                <div class="grid">
                                    <div class="field">
                                        <label>Label</label>
                                        <input type="text" name="popular_origins[{{ $originIndex }}][label]" value="{{ data_get($origin, 'label') }}" placeholder="Hazratganj">
                                    </div>
                                    <div class="field">
                                        <label>Category</label>
                                        <input type="text" name="popular_origins[{{ $originIndex }}][category]" value="{{ data_get($origin, 'category') }}" placeholder="City Center">
                                    </div>
                                </div>
                                <div class="grid-3">
                                    <div class="field">
                                        <label>Latitude</label>
                                        <input type="number" step="0.0000001" name="popular_origins[{{ $originIndex }}][latitude]" value="{{ data_get($origin, 'latitude') }}" data-lat-target="origin">
                                    </div>
                                    <div class="field">
                                        <label>Longitude</label>
                                        <input type="number" step="0.0000001" name="popular_origins[{{ $originIndex }}][longitude]" value="{{ data_get($origin, 'longitude') }}" data-lng-target="origin">
                                    </div>
                                    <div class="field">
                                        <label>Display Order</label>
                                        <input type="number" min="0" name="popular_origins[{{ $originIndex }}][display_order]" value="{{ data_get($origin, 'display_order', $originIndex) }}">
                                    </div>
                                </div>
                            </div>
                        @empty
                        @endforelse
                    </div>
                    <div class="actions" style="margin-top:16px;">
                        <button type="button" class="btn btn-secondary" id="add-origin">Add Popular Origin</button>
                    </div>
                    <div class="landmark-fetch-panel">
                        <h3 class="section-title" style="margin-bottom:10px;">Fetch Nearby Landmarks</h3>
                        <p class="section-note" style="margin-bottom:14px;">Category choose karo, radius set karo, result list lao, phir jo landmarks chahiye sirf unko add karo.</p>
                        <div class="landmark-chip-row" id="landmark-category-tabs">
                            @foreach($landmarkFetchCategories as $category)
                                <button
                                    type="button"
                                    class="landmark-chip {{ $loop->first ? 'active' : '' }}"
                                    data-landmark-category="{{ $category['key'] }}"
                                    data-landmark-label="{{ $category['label'] }}"
                                >
                                    <span class="landmark-chip-icon">{{ $category['icon'] }}</span>
                                    <span>{{ $category['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        <div class="landmark-fetch-grid">
                            <div class="field">
                                <label>Selected Category</label>
                                <div class="slug-preview" id="landmark-selected-category">Schools</div>
                            </div>
                            <div class="field">
                                <label>Radius</label>
                                <select id="landmark-radius">
                                    @foreach([2, 5, 10, 20, 50] as $radius)
                                        <option value="{{ $radius }}" @selected($radius === 10)>{{ $radius }} KM</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>Result Limit</label>
                                <select id="landmark-limit">
                                    @foreach([5, 10, 20, 50] as $limit)
                                        <option value="{{ $limit }}" @selected($limit === 10)>{{ $limit }} results</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <button type="button" class="btn btn-secondary" id="fetch-nearby-landmarks">Fetch Results</button>
                            </div>
                        </div>
                        <div class="field-status" id="landmarks-fetch-status" style="margin-top:12px;"></div>
                        <div id="landmark-results" class="landmark-results"></div>
                        <div class="landmark-result-actions">
                            <button type="button" class="btn btn-secondary" id="select-all-landmarks">Select All</button>
                            <button type="button" class="btn btn-secondary" id="clear-landmark-selection">Clear</button>
                            <button type="button" class="btn btn-primary" id="add-selected-landmarks">Add Selected</button>
                        </div>
                    </div>
                    <div id="landmarks-root" style="margin-top:18px;">
                        @forelse(old('landmarks', $landmarks->map(fn($landmark) => [
                            'id' => $landmark->id,
                            'label' => $landmark->label,
                            'type' => $landmark->type,
                            'distance_text' => $landmark->distance_text,
                        ])->toArray()) as $landmarkIndex => $landmark)
                            <div class="landmark-card" data-landmark-index="{{ $landmarkIndex }}">
                                <div class="landmark-head">
                                    <strong>Landmark {{ $landmarkIndex + 1 }}</strong>
                                    <button type="button" class="btn btn-danger remove-landmark">Remove</button>
                                </div>
                                <div class="grid-3">
                                    <div class="field">
                                        <label>Label</label>
                                        <input type="hidden" name="landmarks[{{ $landmarkIndex }}][id]" value="{{ data_get($landmark, 'id') }}">
                                        <input type="text" name="landmarks[{{ $landmarkIndex }}][label]" value="{{ data_get($landmark, 'label') }}">
                                    </div>
                                    <div class="field">
                                        <label>Type</label>
                                        <input type="text" name="landmarks[{{ $landmarkIndex }}][type]" value="{{ data_get($landmark, 'type') }}" placeholder="School / Mall / Metro">
                                    </div>
                                    <div class="field">
                                        <label>Distance</label>
                                        <input type="text" name="landmarks[{{ $landmarkIndex }}][distance_text]" value="{{ data_get($landmark, 'distance_text') }}" placeholder="12 min drive">
                                    </div>
                                </div>
                            </div>
                        @empty
                        @endforelse
                    </div>
                    <div class="actions" style="margin-top:16px;">
                        <button type="button" class="btn btn-primary" id="add-landmark">Add Landmark</button>
                    </div>
                </section>

                <section class="wizard-step {{ $currentStep === 6 ? 'active' : '' }}" data-step="6">
                    <h2 class="section-title">CTA & Publishing</h2>
                    <p class="section-note">Call, WhatsApp, callback, visit, download visibility aur final publish state isi last step se control karo.</p>
                    <div class="grid">
                        <div class="field">
                            <label>Page Status</label>
                            <select name="status">
                                @foreach(['draft' => 'Draft', 'hidden' => 'Hidden', 'published' => 'Published'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $publicPage?->status ?? 'draft') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Public Share URL</label>
                            <div class="slug-preview">
                                @if($shareLink)
                                    {{ route('projects.public-share.show', $shareLink->token) }}
                                @else
                                    Save draft once to generate share token
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="grid" style="margin-top:18px;">
                        <div class="field">
                            <label>Call Phone</label>
                            <input type="text" name="call_phone" value="{{ old('call_phone', $publicPage?->call_phone) }}">
                        </div>
                        <div class="field">
                            <label>WhatsApp Number</label>
                            <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $publicPage?->whatsapp_number) }}">
                        </div>
                        <div class="field">
                            <label>Book Visit URL</label>
                            <input type="url" name="book_visit_url" value="{{ old('book_visit_url', $publicPage?->book_visit_url) }}">
                        </div>
                        <div class="field">
                            <label>Callback URL</label>
                            <input type="url" name="callback_url" value="{{ old('callback_url', $publicPage?->callback_url) }}">
                        </div>
                    </div>

                    <div class="field" style="margin-top:18px;">
                        <label>Section Visibility</label>
                        <div class="check-row">
                            <label class="check"><input type="checkbox" name="show_call" value="1" @checked(old('show_call', $publicPage?->show_call ?? true))> Call</label>
                            <label class="check"><input type="checkbox" name="show_whatsapp" value="1" @checked(old('show_whatsapp', $publicPage?->show_whatsapp ?? true))> WhatsApp</label>
                            <label class="check"><input type="checkbox" name="show_book_visit" value="1" @checked(old('show_book_visit', $publicPage?->show_book_visit ?? true))> Book Visit</label>
                            <label class="check"><input type="checkbox" name="show_request_callback" value="1" @checked(old('show_request_callback', $publicPage?->show_request_callback))> Callback</label>
                            <label class="check"><input type="checkbox" name="show_downloads" value="1" @checked(old('show_downloads', $publicPage?->show_downloads ?? true))> Downloads</label>
                            <label class="check"><input type="checkbox" name="show_video" value="1" @checked(old('show_video', $publicPage?->show_video ?? true))> Video</label>
                            <label class="check"><input type="checkbox" name="show_tour_360" value="1" @checked(old('show_tour_360', $publicPage?->show_tour_360 ?? true))> 360</label>
                        </div>
                    </div>

                    <div class="grid" style="margin-top:18px;">
                        <div class="field">
                            <label>Share Link Status</label>
                            <select name="share_link_status">
                                @foreach(['active' => 'Active', 'revoked' => 'Revoked', 'expired' => 'Expired'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('share_link_status', $shareLink?->status ?? 'active') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Share Link Expiry</label>
                            <input type="datetime-local" name="share_link_expires_at" value="{{ old('share_link_expires_at', optional($shareLink?->expires_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="field">
                            <label>Max Visits</label>
                            <input type="number" name="share_link_max_visits" value="{{ old('share_link_max_visits', $shareLink?->max_visits) }}">
                        </div>
                        <div class="field">
                            <label>Share Link Notes</label>
                            <input type="text" name="share_link_notes" value="{{ old('share_link_notes', $shareLink?->notes) }}">
                        </div>
                    </div>

                    @if($project && $shareLink)
                        <div class="actions" style="margin-top:18px;">
                            <a class="btn btn-secondary" href="{{ route('projects.public-pages.preview', $project) }}" target="_blank">Preview Public Page</a>
                            <a class="btn btn-secondary" href="{{ route('projects.public-pages.analytics', $project) }}" target="_blank">Open Share Analytics</a>
                            @if($shareLink->status === 'revoked')
                                <form method="POST" action="{{ route('projects.public-pages.share-links.reactivate', $shareLink) }}">
                                    @csrf
                                    <button class="btn btn-secondary" type="submit">Reactivate Share Link</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('projects.public-pages.share-links.revoke', $shareLink) }}">
                                    @csrf
                                    <button class="btn btn-secondary" type="submit">Revoke Share Link</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </section>

                <div class="footer-actions">
                    <div class="actions">
                        <button type="button" class="btn btn-secondary" id="save-draft">Save Draft</button>
                        <button type="button" class="btn btn-primary" id="publish-page-button">Publish Public Page</button>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<template id="unit-template">
    <div class="unit-card" data-unit-index="__UNIT__">
        <div class="unit-head">
            <strong>Unit Type __COUNT__</strong>
            <button type="button" class="btn btn-danger remove-unit">Remove Unit</button>
        </div>
        <div class="grid">
            <div class="field">
                <label>Unit Type Name</label>
                <input type="text" name="unit_types[__UNIT__][name]" placeholder="3 BHK">
            </div>
            <div class="field">
                <label>Primary Unit Type</label>
                <div class="check-row">
                    <label class="check"><input type="checkbox" name="unit_types[__UNIT__][is_primary]" value="1"> Mark as featured unit type</label>
                </div>
            </div>
        </div>
        <div class="variant-list"></div>
        <div class="actions" style="margin-top:16px;">
            <button type="button" class="btn btn-secondary add-variant">Add Variant</button>
        </div>
    </div>
</template>

<template id="variant-template">
    <div class="variant-card" data-variant-index="__VARIANT__">
        <div class="variant-head">
            <strong>Variant __COUNT__</strong>
            <div class="actions">
                <button type="button" class="btn btn-secondary clone-variant">Clone</button>
                <button type="button" class="btn btn-danger remove-variant">Remove</button>
            </div>
        </div>
        <div class="variant-body">
            <div class="variant-main">
                <div class="field">
                    <label>Size Label</label>
                    <input type="text" name="unit_types[__UNIT__][variants][__VARIANT__][size_label]" placeholder="1450 Sq.ft.">
                </div>
                <div class="field">
                    <label>Built-up Area</label>
                    <input type="number" step="0.01" class="variant-builtup" name="unit_types[__UNIT__][variants][__VARIANT__][builtup_area_sqft]">
                </div>
                <div class="field">
                    <label>Carpet Area</label>
                    <input type="number" step="0.01" name="unit_types[__UNIT__][variants][__VARIANT__][carpet_area_sqft]">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="unit_types[__UNIT__][variants][__VARIANT__][status]">
                        <option value="available">Available</option>
                        <option value="hold">Hold</option>
                        <option value="sold_out">Sold Out</option>
                        <option value="hidden">Hidden</option>
                    </select>
                </div>
                <div class="field">
                    <label>Variant Rounding</label>
                    <select class="variant-rounding" name="unit_types[__UNIT__][variants][__VARIANT__][rounding_rule]">
                        <option value="">Use default</option>
                        <option value="none">No rounding</option>
                        <option value="nearest_1000">Nearest 1,000</option>
                        <option value="nearest_10000">Nearest 10,000</option>
                        <option value="nearest_100000">Nearest 1 Lakh</option>
                    </select>
                </div>
                <div class="field">
                    <label>Manual Price</label>
                    <div class="check-row" style="margin-bottom:10px;">
                        <label class="check"><input type="checkbox" class="variant-manual-toggle" name="unit_types[__UNIT__][variants][__VARIANT__][use_manual_price]" value="1"> Set manual price</label>
                    </div>
                    <input type="number" step="0.01" class="variant-manual" name="unit_types[__UNIT__][variants][__VARIANT__][manual_price_override]" disabled>
                </div>
                <div class="field">
                    <label>Floor Plan Image</label>
                    <input type="file" name="unit_types[__UNIT__][variants][__VARIANT__][floor_plan_image]" accept="image/*">
                    <small class="uploader-note">Missing upload will use the default floor-plan image automatically.</small>
                </div>
                <div class="field">
                    <label>Details PDF</label>
                    <input type="file" name="unit_types[__UNIT__][variants][__VARIANT__][details_pdf]" accept="application/pdf">
                    <small class="uploader-note">If manual PDF is missing, system-generated details PDF will be used when enough variant data exists.</small>
                </div>
            </div>
            <div class="variant-side">
                <div class="price-preview-card">
                    <label>Auto Price Preview</label>
                    <div class="variant-price-preview price-preview-value pending">Price pending</div>
                </div>
                <div class="variant-side-card">
                    <label>Visibility & Tags</label>
                    <div class="check-stack">
                        <label class="check"><input type="checkbox" name="unit_types[__UNIT__][variants][__VARIANT__][visible_on_public_page]" value="1" checked> Visible</label>
                        <label class="check"><input type="checkbox" name="unit_types[__UNIT__][variants][__VARIANT__][is_price_on_request]" value="1"> Price on request</label>
                        <label class="check"><input type="checkbox" name="unit_types[__UNIT__][variants][__VARIANT__][is_featured]" value="1"> Featured</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="asset-template">
    <div class="asset-card" data-asset-index="__ASSET__">
        <div class="asset-head">
            <strong>Asset __COUNT__</strong>
            <button type="button" class="btn btn-danger remove-asset">Remove</button>
        </div>
        <div class="grid">
            <div class="field">
                <label>Asset Type</label>
                <select name="assets[__ASSET__][asset_type]">
                    <option value="brochure">PDF</option>
                    <option value="price_sheet">Price Sheet</option>
                    <option value="video">Video URL</option>
                    <option value="tour_360">360 URL</option>
                </select>
            </div>
            <div class="field">
                <label>Title</label>
                <input type="text" name="assets[__ASSET__][title]">
            </div>
            <div class="field">
                <label>External URL</label>
                <input type="url" name="assets[__ASSET__][external_url]">
            </div>
            <div class="field">
                <label>Tracking Key</label>
                <input type="text" name="assets[__ASSET__][tracking_key]">
            </div>
            <div class="field">
                <label>Media Tag</label>
                <select name="assets[__ASSET__][meta][category]">
                    <option value="">General</option>
                    <option value="walkthrough">Walkthrough</option>
                    <option value="site">Site Update</option>
                </select>
            </div>
            <div class="field">
                <label>File Upload</label>
                <input type="file" name="assets[__ASSET__][file]">
            </div>
            <div class="field">
                <label>Preview Image</label>
                <input type="file" name="assets[__ASSET__][preview_image]" accept="image/*">
            </div>
            <div class="field">
                <label>Featured Placement</label>
                <div class="check-row">
                    <label class="check">
                        <input type="checkbox" name="assets[__ASSET__][is_featured]" value="1">
                        Feature above Unit Plans
                    </label>
                </div>
                <small>Only one video should be featured here.</small>
            </div>
        </div>
    </div>
</template>

<template id="landmark-template">
    <div class="landmark-card" data-landmark-index="__LANDMARK__">
        <div class="landmark-head">
            <strong>Landmark __COUNT__</strong>
            <button type="button" class="btn btn-danger remove-landmark">Remove</button>
        </div>
        <div class="grid-3">
            <div class="field">
                <label>Label</label>
                <input type="text" name="landmarks[__LANDMARK__][label]">
            </div>
            <div class="field">
                <label>Type</label>
                <input type="text" name="landmarks[__LANDMARK__][type]">
            </div>
            <div class="field">
                <label>Distance</label>
                <input type="text" name="landmarks[__LANDMARK__][distance_text]">
            </div>
        </div>
    </div>
</template>

<template id="popular-origin-template">
    <div class="landmark-card" data-origin-index="__ORIGIN__">
        <div class="landmark-head">
            <strong>Popular Origin __COUNT__</strong>
            <button type="button" class="btn btn-danger remove-origin">Remove</button>
        </div>
        <div class="field" style="margin-bottom:14px;">
            <label>Google Maps URL</label>
            <div class="field-action">
                <input type="url" data-map-url placeholder="Paste Google Maps share URL for this origin">
                <button type="button" class="btn btn-secondary js-fill-coordinates" data-target="origin">Auto Fill Lat / Lng</button>
            </div>
            <div class="field-status" data-map-status="origin"></div>
        </div>
        <div class="grid">
            <div class="field">
                <label>Label</label>
                <input type="text" name="popular_origins[__ORIGIN__][label]">
            </div>
            <div class="field">
                <label>Category</label>
                <input type="text" name="popular_origins[__ORIGIN__][category]">
            </div>
        </div>
        <div class="grid-3">
            <div class="field">
                <label>Latitude</label>
                <input type="number" step="0.0000001" name="popular_origins[__ORIGIN__][latitude]" data-lat-target="origin">
            </div>
            <div class="field">
                <label>Longitude</label>
                <input type="number" step="0.0000001" name="popular_origins[__ORIGIN__][longitude]" data-lng-target="origin">
            </div>
            <div class="field">
                <label>Display Order</label>
                <input type="number" min="0" name="popular_origins[__ORIGIN__][display_order]" value="__ORIGIN__">
            </div>
        </div>
    </div>
</template>

<template id="other-charge-template">
    <div class="asset-card other-charge-card" data-charge-index="__CHARGE__">
        <div class="asset-head">
            <strong>Charge __COUNT__</strong>
            <button type="button" class="btn btn-danger remove-charge">Remove</button>
        </div>
        <div class="grid-3">
            <div class="field">
                <label>Charge Name</label>
                <input type="text" name="other_charges[__CHARGE__][name]" value="__NAME__" placeholder="Parking">
            </div>
            <div class="field">
                <label>Value</label>
                <input type="text" class="other-charge-value" name="other_charges[__CHARGE__][value]" placeholder="300000">
            </div>
            <div class="field">
                <label>Type</label>
                <select class="other-charge-type" name="other_charges[__CHARGE__][type]">
                    <option value="fixed">Fixed</option>
                    <option value="per_sqft">Per Sq.ft.</option>
                    <option value="percent">Percent</option>
                    <option value="on_request">On Request</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script>
    (() => {
        const form = document.getElementById('public-page-form');
        const currentStepInput = document.getElementById('current_step');
        const nextStepInput = document.getElementById('next_step');
        const stepButtons = [...document.querySelectorAll('[data-step-nav]')];
        const stepPanels = [...document.querySelectorAll('.wizard-step')];
        let activeStep = Number(currentStepInput.value || 1);
        let isDirty = false;

        function activateStep(step) {
            activeStep = Math.min(6, Math.max(1, step));
            currentStepInput.value = activeStep;
            nextStepInput.value = activeStep;
            stepPanels.forEach((panel) => panel.classList.toggle('active', Number(panel.dataset.step) === activeStep));
            stepButtons.forEach((button) => button.classList.toggle('active', Number(button.dataset.stepNav) === activeStep));
        }

        function replaceTokens(templateId, replacements) {
            let html = document.getElementById(templateId).innerHTML;
            Object.entries(replacements).forEach(([key, value]) => {
                html = html.replaceAll(`__${key}__`, value);
            });
            return html;
        }

        function addUnit() {
            const root = document.getElementById('unit-types-root');
            const index = root.querySelectorAll('.unit-card').length;
            root.insertAdjacentHTML('beforeend', replaceTokens('unit-template', { UNIT: index, COUNT: index + 1 }));
        }

        function addVariant(unitCard, clonedNode = null) {
            const unitIndex = [...document.querySelectorAll('.unit-card')].indexOf(unitCard);
            const list = unitCard.querySelector('.variant-list');
            const variantIndex = list.querySelectorAll('.variant-card').length;
            if (clonedNode) {
                list.appendChild(clonedNode);
                reindexAll();
                return;
            }
            list.insertAdjacentHTML('beforeend', replaceTokens('variant-template', { UNIT: unitIndex, VARIANT: variantIndex, COUNT: variantIndex + 1 }));
            computeAllPrices();
        }

        function addAsset() {
            const root = document.getElementById('assets-root');
            const index = root.querySelectorAll('.asset-card').length;
            root.insertAdjacentHTML('beforeend', replaceTokens('asset-template', { ASSET: index, COUNT: index + 1 }));
        }

        function addLandmark() {
            const root = document.getElementById('landmarks-root');
            const index = root.querySelectorAll('.landmark-card').length;
            root.insertAdjacentHTML('beforeend', replaceTokens('landmark-template', { LANDMARK: index, COUNT: index + 1 }));
        }

        function appendLandmarkRow(landmark = {}) {
            const root = document.getElementById('landmarks-root');
            const index = root.querySelectorAll('.landmark-card').length;
            root.insertAdjacentHTML('beforeend', replaceTokens('landmark-template', { LANDMARK: index, COUNT: index + 1 }));
            const card = root.querySelector('.landmark-card:last-child');
            if (!card) return;
            card.querySelector(`input[name="landmarks[${index}][label]"]`).value = landmark.label || '';
            card.querySelector(`input[name="landmarks[${index}][type]"]`).value = landmark.type || '';
            card.querySelector(`input[name="landmarks[${index}][distance_text]"]`).value = landmark.distance_text || '';
        }

        function renderLandmarkResults(landmarks = [], categoryLabel = '') {
            const root = document.getElementById('landmark-results');
            if (!root) return;
            if (!landmarks.length) {
                root.innerHTML = '';
                return;
            }

            root.innerHTML = landmarks.map((landmark, index) => `
                <label class="landmark-result-card">
                    <input type="checkbox" data-landmark-select="${index}">
                    <span class="landmark-result-body">
                        <span class="landmark-result-title">${landmark.label || ''}</span>
                        <span class="landmark-result-meta">
                            <span>${landmark.type || categoryLabel || ''}</span>
                            <span>${landmark.distance_text || ''}</span>
                        </span>
                        ${landmark.address ? `<span class="landmark-result-address">${landmark.address}</span>` : ''}
                    </span>
                </label>
            `).join('');
        }

        function addPopularOrigin() {
            const root = document.getElementById('popular-origins-root');
            const index = root.querySelectorAll('.landmark-card').length;
            root.insertAdjacentHTML('beforeend', replaceTokens('popular-origin-template', { ORIGIN: index, COUNT: index + 1 }));
        }

        function addOtherCharge(name = '') {
            const root = document.getElementById('other-charges-root');
            const index = root.querySelectorAll('.other-charge-card').length;
            root.insertAdjacentHTML('beforeend', replaceTokens('other-charge-template', { CHARGE: index, COUNT: index + 1, NAME: name }));
        }

        function reindexAll() {
            document.querySelectorAll('.unit-card').forEach((unitCard, unitIndex) => {
                unitCard.dataset.unitIndex = unitIndex;
                unitCard.querySelector('.unit-head strong').textContent = `Unit Type ${unitIndex + 1}`;
                unitCard.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/unit_types\[\d+\]/, `unit_types[${unitIndex}]`);
                });

                unitCard.querySelectorAll('.variant-card').forEach((variantCard, variantIndex) => {
                    variantCard.dataset.variantIndex = variantIndex;
                    variantCard.querySelector('.variant-head strong').textContent = `Variant ${variantIndex + 1}`;
                    variantCard.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name
                            .replace(/unit_types\[\d+\]/, `unit_types[${unitIndex}]`)
                            .replace(/variants\]\[\d+\]/, `variants][${variantIndex}]`);
                    });
                });
            });

            document.querySelectorAll('.asset-card').forEach((card, index) => {
                card.dataset.assetIndex = index;
                card.querySelector('.asset-head strong').textContent = `Asset ${index + 1}`;
                card.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/assets\[\d+\]/, `assets[${index}]`);
                });
            });

            document.querySelectorAll('#landmarks-root .landmark-card').forEach((card, index) => {
                card.dataset.landmarkIndex = index;
                card.querySelector('.landmark-head strong').textContent = `Landmark ${index + 1}`;
                card.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/landmarks\[\d+\]/, `landmarks[${index}]`);
                });
            });

            document.querySelectorAll('#popular-origins-root .landmark-card').forEach((card, index) => {
                card.dataset.originIndex = index;
                card.querySelector('.landmark-head strong').textContent = `Popular Origin ${index + 1}`;
                card.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/popular_origins\[\d+\]/, `popular_origins[${index}]`);
                });
            });

            document.querySelectorAll('#other-charges-root .other-charge-card').forEach((card, index) => {
                card.dataset.chargeIndex = index;
                card.querySelector('.asset-head strong').textContent = `Charge ${index + 1}`;
                card.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/other_charges\[\d+\]/, `other_charges[${index}]`);
                });
            });
        }

        function formatIndian(value) {
            if (!value) return '';
            const number = Number(value);
            if (!Number.isFinite(number)) return '';
            return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(number);
        }

        function roundPrice(price, rule) {
            if (!Number.isFinite(price)) return null;
            if (rule === 'nearest_1000') return Math.round(price / 1000) * 1000;
            if (rule === 'nearest_10000') return Math.round(price / 10000) * 10000;
            if (rule === 'nearest_100000') return Math.round(price / 100000) * 100000;
            return price;
        }

        function computeAllPrices() {
            const defaultRate = parseFloat(document.getElementById('default_rate').value || '0');
            const defaultRounding = document.getElementById('default_rounding').value || 'none';

            document.querySelectorAll('.variant-card').forEach((card) => {
                const builtup = parseFloat(card.querySelector('.variant-builtup')?.value || '0');
                const rate = defaultRate;
                const rounding = card.querySelector('.variant-rounding')?.value || defaultRounding;
                const manualToggle = card.querySelector('.variant-manual-toggle');
                const manualField = card.querySelector('.variant-manual');
                const useManual = !!manualToggle?.checked;
                if (manualField) {
                    manualField.disabled = !useManual;
                }
                const manual = useManual ? parseFloat(manualField?.value || '') : NaN;
                const preview = card.querySelector('.variant-price-preview');
                let price = builtup && rate ? roundPrice(builtup * rate, rounding) : null;
                if (Number.isFinite(manual)) {
                    price = manual;
                }
                preview.textContent = price ? `INR ${formatIndian(price)}` : 'Price pending';
                preview.classList.toggle('pending', !price);
            });
        }

        document.getElementById('add-unit-type').addEventListener('click', () => {
            addUnit();
            isDirty = true;
        });
        document.getElementById('add-asset').addEventListener('click', () => {
            addAsset();
            isDirty = true;
        });
        document.getElementById('add-landmark').addEventListener('click', () => {
            addLandmark();
            isDirty = true;
        });
        document.getElementById('fetch-nearby-landmarks')?.addEventListener('click', async () => {
            const status = document.getElementById('landmarks-fetch-status');
            const resultsRoot = document.getElementById('landmark-results');
            const latitude = parseFloat(document.querySelector('[name="latitude"]')?.value || '');
            const longitude = parseFloat(document.querySelector('[name="longitude"]')?.value || '');
            const activeCategoryButton = document.querySelector('[data-landmark-category].active');
            const category = activeCategoryButton?.dataset.landmarkCategory || 'school';
            const categoryLabel = activeCategoryButton?.dataset.landmarkLabel || 'Schools';
            const radiusKm = Number(document.getElementById('landmark-radius')?.value || 10);
            const limit = Number(document.getElementById('landmark-limit')?.value || 10);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                if (status) status.textContent = 'Pehle project latitude aur longitude fill karo.';
                return;
            }

            if (status) status.textContent = `${categoryLabel} fetch ho rahe hain...`;
            if (resultsRoot) resultsRoot.innerHTML = '';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(fetchNearbyLandmarksUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                    },
                    body: JSON.stringify({ latitude, longitude, category, radius_km: radiusKm, limit }),
                });
                const payload = await response.json();

                if (!response.ok || !payload?.ok) {
                    throw new Error(payload?.message || 'Nearby landmarks fetch nahi ho paaya.');
                }

                window.__fetchedLandmarks = payload.landmarks || [];
                renderLandmarkResults(window.__fetchedLandmarks, payload.category_label || categoryLabel);
                if (status) {
                    status.textContent = payload.landmarks?.length
                        ? `${payload.landmarks.length} ${payload.category_label || categoryLabel} results mil gaye. Jo chahiye unhe select karke add karo.`
                        : 'Nearby landmarks nahi mile.';
                }
            } catch (error) {
                if (status) status.textContent = error?.message || 'Nearby landmarks fetch nahi ho paaya.';
            }
        });
        document.getElementById('add-origin').addEventListener('click', () => {
            addPopularOrigin();
            isDirty = true;
        });
        document.getElementById('select-all-landmarks')?.addEventListener('click', () => {
            document.querySelectorAll('[data-landmark-select]').forEach((checkbox) => {
                checkbox.checked = true;
            });
        });
        document.getElementById('clear-landmark-selection')?.addEventListener('click', () => {
            document.querySelectorAll('[data-landmark-select]').forEach((checkbox) => {
                checkbox.checked = false;
            });
        });
        document.getElementById('add-selected-landmarks')?.addEventListener('click', () => {
            const selectedIndexes = [...document.querySelectorAll('[data-landmark-select]:checked')].map((checkbox) => Number(checkbox.dataset.landmarkSelect));
            const fetchedLandmarks = window.__fetchedLandmarks || [];
            const selectedLandmarks = selectedIndexes
                .map((index) => fetchedLandmarks[index])
                .filter(Boolean);
            const status = document.getElementById('landmarks-fetch-status');

            if (!selectedLandmarks.length) {
                if (status) status.textContent = 'Pehle results me se landmarks select karo.';
                return;
            }

            selectedLandmarks.forEach((landmark) => appendLandmarkRow(landmark));
            reindexAll();
            isDirty = true;
            if (status) {
                status.textContent = `${selectedLandmarks.length} landmarks list me add ho gaye. Save kar do.`;
            }
        });
        document.querySelectorAll('[data-landmark-category]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('[data-landmark-category]').forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
                const selectedCategory = document.getElementById('landmark-selected-category');
                if (selectedCategory) {
                    selectedCategory.textContent = button.dataset.landmarkLabel || button.textContent.trim();
                }
            });
        });
        document.getElementById('add-other-charge')?.addEventListener('click', () => {
            addOtherCharge();
            isDirty = true;
        });
        document.querySelectorAll('.add-charge-preset').forEach((button) => {
            button.addEventListener('click', () => {
                addOtherCharge(button.dataset.chargeName || '');
                isDirty = true;
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.matches('.add-variant')) {
                addVariant(event.target.closest('.unit-card'));
                isDirty = true;
            }
            if (event.target.matches('.remove-unit')) {
                event.target.closest('.unit-card')?.remove();
                reindexAll();
                computeAllPrices();
                isDirty = true;
            }
            if (event.target.matches('.remove-variant')) {
                event.target.closest('.variant-card')?.remove();
                reindexAll();
                computeAllPrices();
                isDirty = true;
            }
            if (event.target.matches('.clone-variant')) {
                const original = event.target.closest('.variant-card');
                const clone = original.cloneNode(true);
                clone.querySelectorAll('input').forEach((input) => {
                    if (input.type === 'file' || input.type === 'hidden') {
                        input.value = '';
                    }
                });
                addVariant(event.target.closest('.unit-card'), clone);
                computeAllPrices();
                isDirty = true;
            }
            if (event.target.matches('.remove-asset')) {
                event.target.closest('.asset-card')?.remove();
                reindexAll();
                isDirty = true;
            }
            if (event.target.matches('.remove-landmark')) {
                event.target.closest('.landmark-card')?.remove();
                reindexAll();
                isDirty = true;
            }
            if (event.target.matches('.remove-origin')) {
                event.target.closest('.landmark-card')?.remove();
                reindexAll();
                isDirty = true;
            }
            if (event.target.matches('.remove-charge')) {
                event.target.closest('.other-charge-card')?.remove();
                reindexAll();
                isDirty = true;
            }
        });

        document.addEventListener('input', (event) => {
            if (event.target.matches('input, textarea, select')) {
                isDirty = true;
            }
            if (event.target.matches('#default_rate, #default_rounding, .variant-builtup, .variant-rounding, .variant-manual, .variant-manual-toggle')) {
                computeAllPrices();
            }
        });

        document.addEventListener('change', (event) => {
            if (event.target.matches('.other-charge-type')) {
                const card = event.target.closest('.other-charge-card');
                const valueInput = card?.querySelector('.other-charge-value');
                const onRequest = event.target.value === 'on_request';
                if (valueInput) {
                    valueInput.disabled = onRequest;
                    if (onRequest) {
                        valueInput.value = '';
                    }
                }
                isDirty = true;
            }
        });

        stepButtons.forEach((button) => {
            button.addEventListener('click', () => activateStep(Number(button.dataset.stepNav)));
        });

        const publishAfterSaveInput = document.getElementById('publish_after_save');
        const forcePublishInput = document.getElementById('force_publish');
        const shouldForcePublish = @json(session()->has('publish_warnings'));
        const liveShareUrlToOpen = @json(session('open_live_share_link'));
        const resolveMapCoordinatesUrl = @json(route('projects.public-pages.resolve-map-coordinates'));
        const fetchNearbyLandmarksUrl = @json(route('projects.public-pages.fetch-nearby-landmarks'));

        const submitWizardForm = ({ nextStep = activeStep, publishAfterSave = false, forcePublish = false } = {}) => {
            nextStepInput.value = nextStep;
            if (publishAfterSaveInput) {
                publishAfterSaveInput.value = publishAfterSave ? '1' : '0';
            }
            if (forcePublishInput) {
                forcePublishInput.value = forcePublish ? '1' : '0';
            }
            isDirty = false;
            form.submit();
        };

        document.getElementById('save-draft').addEventListener('click', () => {
            submitWizardForm({ nextStep: activeStep });
        });
        document.getElementById('publish-page-button')?.addEventListener('click', () => {
            submitWizardForm({ nextStep: 6, publishAfterSave: true, forcePublish: shouldForcePublish });
        });

        document.querySelectorAll('.js-post-action').forEach((button) => {
            button.addEventListener('click', () => {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const action = button.dataset.url;
                if (!action || !csrf) return;
                const helperForm = document.createElement('form');
                helperForm.method = 'POST';
                helperForm.action = action;
                helperForm.style.display = 'none';
                helperForm.innerHTML = `<input type="hidden" name="_token" value="${csrf}">`;
                document.body.appendChild(helperForm);
                helperForm.submit();
            });
        });

        const parseCoordinatesFromUrl = (rawValue) => {
            const value = (rawValue || '').trim();
            if (!value) return null;

            const patterns = [
                /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
                /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
                /[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
                /[?&]ll=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
                /(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
            ];

            for (const pattern of patterns) {
                const match = value.match(pattern);
                if (match) {
                    return {
                        latitude: Number(match[1]),
                        longitude: Number(match[2]),
                    };
                }
            }

            return null;
        };

        const fillCoordinates = async (button) => {
            const card = button.closest('.landmark-card');
            const scope = card || form;
            const mapUrlInput = scope.querySelector('[data-map-url]');
            const latInput = scope.querySelector(`[data-lat-target="${button.dataset.target}"]`);
            const lngInput = scope.querySelector(`[data-lng-target="${button.dataset.target}"]`);
            const status = scope.querySelector(`[data-map-status="${button.dataset.target}"]`);

            if (!mapUrlInput || !latInput || !lngInput) {
                return;
            }

            const coords = parseCoordinatesFromUrl(mapUrlInput.value);
            if (coords) {
                latInput.value = coords.latitude.toFixed(7);
                lngInput.value = coords.longitude.toFixed(7);
                if (status) {
                    status.textContent = `Latitude ${latInput.value} aur longitude ${lngInput.value} fill ho gaya.`;
                }
                isDirty = true;
                return;
            }

            if (status) {
                status.textContent = 'Link resolve ho raha hai...';
            }

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(resolveMapCoordinatesUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                    },
                    body: JSON.stringify({ url: mapUrlInput.value }),
                });

                const payload = await response.json();
                if (!response.ok || !payload?.ok) {
                    throw new Error(payload?.message || 'Valid Google Maps URL ya coordinates format nahi mila.');
                }

                latInput.value = Number(payload.latitude).toFixed(7);
                lngInput.value = Number(payload.longitude).toFixed(7);
                if (status) {
                    status.textContent = `Latitude ${latInput.value} aur longitude ${lngInput.value} fill ho gaya.`;
                }
                isDirty = true;
            } catch (error) {
                if (status) {
                    status.textContent = error?.message || 'Valid Google Maps URL ya coordinates format nahi mila.';
                }
            }
        };

        window.addEventListener('beforeunload', (event) => {
            if (!isDirty) return;
            event.preventDefault();
            event.returnValue = '';
        });

        form.addEventListener('submit', () => {
            isDirty = false;
        });

        document.addEventListener('click', (event) => {
            if (event.target.matches('.js-fill-coordinates')) {
                fillCoordinates(event.target);
            }
        });

        activateStep(activeStep);
        computeAllPrices();

        if (liveShareUrlToOpen) {
            window.setTimeout(() => {
                window.open(liveShareUrlToOpen, '_blank', 'noopener');
            }, 150);
        }
    })();
</script>
</body>
</html>
