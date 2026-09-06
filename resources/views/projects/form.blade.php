@extends('layouts.app')

@section('title', ($project ? 'Edit Project Builder' : 'Create Project Builder') . ' - ' . brand_name())
@section('page-title', $project ? 'Edit Project Builder' : 'Create Project Builder')

@section('content')
@php
    $publicPage = $project?->publicPage;
    $publicStatus = $publicPage?->status ?? 'draft';
    $statusStyles = [
        'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
        'published' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'hidden' => 'bg-slate-100 text-slate-700 border-slate-200',
    ];
    $publicStatusLabel = ucfirst($publicStatus);
    $towerUnitTypes = $project?->towers?->flatMap(fn ($tower) => $tower->unitTypes ?? collect()) ?? collect();
    $hasUnitInventory = ($project?->unitTypes?->isNotEmpty() ?? false) || $towerUnitTypes->isNotEmpty();
    $publishChecklist = [];

    if ($project) {
        if (!filled($project->name)) {
            $publishChecklist[] = 'Project name';
        }
        if (!filled($project->city)) {
            $publishChecklist[] = 'City';
        }
        if (!filled($project->project_status)) {
            $publishChecklist[] = 'Project status';
        }
        if (!filled($project->possession_date)) {
            $publishChecklist[] = 'Possession date';
        }
        if (!$hasUnitInventory) {
            $publishChecklist[] = 'At least one unit type';
        }
    }
@endphp
<div id="project-public-builder" class="max-w-6xl mx-auto">
    <div class="mb-6 rounded-3xl border border-emerald-100 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-6 lg:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Project Builder Flow</p>
                        <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ $project ? 'Edit Project Builder' : 'Create Project Builder' }}</h1>
                        <p class="mt-2 max-w-3xl text-sm text-slate-600">
                            Sari project detail isi single page par save hogi. Public share page same data se render hoga; Publish Public Page se hi live hoga.
                        </p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-6">
                        <a href="#project-core" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 no-underline">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-700">Core</div>
                            <div class="mt-1 text-xs text-slate-600">Identity</div>
                        </a>
                        <a href="#project-pricing" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 no-underline">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Pricing</div>
                            <div class="mt-1 text-xs text-slate-600">Units</div>
                        </a>
                        <a href="#project-media" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 no-underline">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Media</div>
                            <div class="mt-1 text-xs text-slate-600">Gallery</div>
                        </a>
                        <a href="#project-location" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 no-underline">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Location</div>
                            <div class="mt-1 text-xs text-slate-600">Map</div>
                        </a>
                        <a href="#project-cta" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 no-underline">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">CTA</div>
                            <div class="mt-1 text-xs text-slate-600">Contacts</div>
                        </a>
                        <a href="#project-publish" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 no-underline">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Publish</div>
                            <div class="mt-1 text-xs text-slate-600">Go live</div>
                        </a>
                    </div>
                </div>

                <div class="w-full max-w-sm rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Public Status</div>
                            <div class="mt-2 inline-flex items-center rounded-full border px-3 py-1 text-sm font-semibold {{ $statusStyles[$publicStatus] ?? $statusStyles['draft'] }}">
                                {{ $publicStatusLabel }}
                            </div>
                        </div>
                        @if($project)
                            <div class="text-right">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Project ID</div>
                                <div class="mt-2 text-base font-semibold text-slate-900">#{{ $project->id }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-slate-600">
                        <div class="flex items-start justify-between gap-3">
                            <span>Core project save</span>
                            <span class="font-medium text-slate-900">Draft only</span>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <span>Public layout source</span>
                            <span class="font-medium text-slate-900">Single builder page</span>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <span>Go live trigger</span>
                            <span class="font-medium text-slate-900">Publish Public Page</span>
                        </div>
                    </div>

                    @if($project)
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('projects.public-pages.preview', $project) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50">
                                <i class="fas fa-eye mr-2"></i> Preview Public Page
                            </a>
                            <button type="button" onclick="document.getElementById('publish-public-page-form')?.submit()" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                <i class="fas fa-globe mr-2"></i> Publish Public Page
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 lg:p-8">
        @if($project)
            <form id="publish-public-page-form" method="POST" action="{{ route('projects.public-pages.publish', $project) }}" class="hidden">
                @csrf
            </form>
        @endif

        <form method="POST" action="{{ $project ? route('projects.update', $project) : route('projects.store') }}" enctype="multipart/form-data">
            @csrf
            @if($project)
                @method('PUT')
            @endif
            <input type="hidden" name="next_action" id="next_action" value="stay">
            <input type="hidden" name="next_anchor" id="next_anchor" value="project-core">
            <input type="hidden" name="presentation_fields_present" value="1">

            @if(session('success') || session('status'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                    {{ session('success') ?? session('status') }}
                </div>
            @endif

            @if(session('publish_warnings'))
                @php $warnings = is_array(session('publish_warnings')) ? session('publish_warnings') : [session('publish_warnings')]; @endphp
                <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">
                    <div class="font-semibold">Publish ke liye ye details complete karo:</div>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div id="project-publish" class="mb-8 rounded-3xl border border-emerald-100 bg-emerald-50/60 p-5 lg:p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Publish Checklist</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ !$project ? 'Save draft first' : (empty($publishChecklist) ? 'Ready to publish' : 'Required before publish') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">Draft save relaxed hai. Public page live karne ke time required checks lagenge.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if(!$project)
                            <span class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-slate-600">Project not saved yet</span>
                        @elseif(empty($publishChecklist))
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">All required data ready</span>
                        @else
                            @foreach($publishChecklist as $item)
                                <span class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-amber-700">{{ $item }}</span>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <!-- Basic Info Section -->
            <div id="project-core" class="mb-8 rounded-3xl border border-slate-200 bg-slate-50/60 p-5 lg:p-6">
                <div class="mb-5 flex flex-col gap-2 border-b border-slate-200 pb-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 1</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Project Identity</h3>
                        <p class="mt-1 text-sm text-slate-600">Core ownership, brand signal, project type, and base overview.</p>
                    </div>
                    <div class="text-xs text-slate-500">These fields power builder fallback values.</div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="min-w-0">
                        <label for="builder_id" class="block text-sm font-medium text-gray-700 mb-2">Builder</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select name="builder_id" id="builder_id" class="flex-1 min-w-0 px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" onchange="loadBuilderContacts(this.value)">
                                <option value="">Select Builder</option>
                                @foreach($builders as $builderOption)
                                    <option value="{{ $builderOption->id }}" {{ old('builder_id', $project ? $project->builder_id : (session('selected_builder_id') == $builderOption->id ? 'selected' : '')) == $builderOption->id ? 'selected' : '' }}>
                                        {{ $builderOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                                <div class="flex gap-2 flex-shrink-0">
                                    <a href="{{ route('builders.create', ['return_to' => 'project_form']) }}" class="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 whitespace-nowrap inline-flex items-center text-sm">
                                        <i class="fas fa-plus mr-1"></i> Create Builder
                                    </a>
                                    @if($project && $project->builder)
                                        <a href="{{ route('builders.edit', $project->builder) }}" target="_blank" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 whitespace-nowrap inline-flex items-center text-sm">
                                            <i class="fas fa-edit mr-1"></i> Edit Builder
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="min-w-0">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Project Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $project ? $project->name : '') }}"
                               class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">Project Logo</label>
                    <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/jpg,image/webp"
                           class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                    @if($project && $project->logo)
                        <div class="mt-2">
                            <img src="{{ $project->logo_url }}" alt="Current logo" class="h-20 w-20 rounded object-cover">
                        </div>
                    @endif
                    <p class="mt-1 text-sm text-gray-500">Max 2MB. Formats: JPG, PNG, WebP</p>
                </div>

                <div class="mb-4">
                    <label for="short_overview" class="block text-sm font-medium text-gray-700 mb-2">Short Overview</label>
                    <textarea name="short_overview" id="short_overview" rows="3" maxlength="2000"
                              class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('short_overview', $project ? $project->short_overview : '') }}</textarea>
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-500">
                        <span>Ideal 45-70 words. Public builder can refine premium copy later.</span>
                        <span><span data-word-count-for="short_overview">0</span> words</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="project_type" class="block text-sm font-medium text-gray-700 mb-2">Project Type</label>
                        <select name="project_type" id="project_type" onchange="toggleResidentialSubType()" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Type</option>
                            <option value="residential" {{ old('project_type', $project ? $project->project_type : '') === 'residential' ? 'selected' : '' }}>Residential</option>
                            <option value="commercial" {{ old('project_type', $project ? $project->project_type : '') === 'commercial' ? 'selected' : '' }}>Commercial</option>
                            <option value="mixed" {{ old('project_type', $project ? $project->project_type : '') === 'mixed' ? 'selected' : '' }}>Mixed</option>
                        </select>
                    </div>

                    <div id="residential_sub_type_container" style="display: {{ old('project_type', $project ? $project->project_type : '') === 'residential' ? 'block' : 'none' }};">
                        <label for="residential_sub_type" class="block text-sm font-medium text-gray-700 mb-2">Residential Sub-Type</label>
                        <select name="residential_sub_type" id="residential_sub_type" onchange="toggleTowersSection()" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Type</option>
                            <option value="plot" {{ old('residential_sub_type', $project ? $project->residential_sub_type : '') === 'plot' ? 'selected' : '' }}>Plot</option>
                            <option value="flat" {{ old('residential_sub_type', $project ? $project->residential_sub_type : '') === 'flat' ? 'selected' : '' }}>Flat</option>
                            <option value="villa" {{ old('residential_sub_type', $project ? $project->residential_sub_type : '') === 'villa' ? 'selected' : '' }}>Villa</option>
                        </select>
                    </div>

                    <div>
                        <label for="project_status" class="block text-sm font-medium text-gray-700 mb-2">Project Status</label>
                        <select name="project_status" id="project_status" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Status</option>
                            <option value="prelaunch" {{ old('project_status', $project ? $project->project_status : '') === 'prelaunch' ? 'selected' : '' }}>Prelaunch</option>
                            <option value="under_construction" {{ old('project_status', $project ? $project->project_status : '') === 'under_construction' ? 'selected' : '' }}>Under Construction</option>
                            <option value="ready" {{ old('project_status', $project ? $project->project_status : '') === 'ready' ? 'selected' : '' }}>Ready</option>
                        </select>
                    </div>

                    <div>
                        <label for="availability_type" class="block text-sm font-medium text-gray-700 mb-2">Availability Type</label>
                        <select name="availability_type" id="availability_type" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="fresh" {{ old('availability_type', $project ? $project->availability_type : 'fresh') === 'fresh' ? 'selected' : '' }}>Fresh</option>
                            <option value="resale" {{ old('availability_type', $project ? $project->availability_type : 'fresh') === 'resale' ? 'selected' : '' }}>Resale</option>
                            <option value="both" {{ old('availability_type', $project ? $project->availability_type : 'fresh') === 'both' ? 'selected' : '' }}>Both</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Location Section -->
            <div id="project-location" class="mb-8 rounded-3xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 2</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Location</h3>
                    <p class="mt-1 text-sm text-slate-600">City and locality stay here as master data. Public storytelling can be refined in the builder.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">City</label>
                        <input type="text" name="city" id="city" value="{{ old('city', $project ? $project->city : '') }}"
                               class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="area" class="block text-sm font-medium text-gray-700 mb-2">Area / Locality</label>
                        <input type="text" name="area" id="area" value="{{ old('area', $project ? $project->area : '') }}"
                               class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="location_summary" class="block text-sm font-medium text-gray-700 mb-2">Public Location Summary</label>
                        <textarea name="location_summary" id="location_summary" rows="3" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Nearby landmarks, connectivity, and location advantage">{{ old('location_summary', $publicPage?->location_summary) }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label for="map_embed" class="block text-sm font-medium text-gray-700 mb-2">Google Map URL / Embed</label>
                        <input type="text" name="map_embed" id="map_embed" value="{{ old('map_embed', $publicPage?->map_embed) }}" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg" placeholder="Paste Google Maps link or embed URL">
                    </div>
                    <div>
                        <label for="latitude" class="block text-sm font-medium text-gray-700 mb-2">Latitude</label>
                        <input type="number" step="any" name="latitude" id="latitude" value="{{ old('latitude', $publicPage?->latitude) }}" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label for="longitude" class="block text-sm font-medium text-gray-700 mb-2">Longitude</label>
                        <input type="number" step="any" name="longitude" id="longitude" value="{{ old('longitude', $publicPage?->longitude) }}" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label for="map_zoom" class="block text-sm font-medium text-gray-700 mb-2">Map Zoom</label>
                        <input type="number" min="1" max="20" name="map_zoom" id="map_zoom" value="{{ old('map_zoom', $publicPage?->map_zoom ?? 14) }}" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                    </div>
                </div>
            </div>

            <!-- Project Size Section -->
            <div id="project-pricing" class="mb-8 rounded-3xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 3</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Project Size</h3>
                    <p class="mt-1 text-sm text-slate-600">Land size and scale details used across sales, analytics, and public fallback cards.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="land_area" class="block text-sm font-medium text-gray-700 mb-2">Land Area</label>
                        <input type="number" step="0.01" name="land_area" id="land_area" value="{{ old('land_area', $project ? $project->land_area : '') }}"
                               class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="land_area_unit" class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                        <select name="land_area_unit" id="land_area_unit" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="sq_ft" {{ old('land_area_unit', $project ? $project->land_area_unit : 'sq_ft') === 'sq_ft' ? 'selected' : '' }}>Sq.ft</option>
                            <option value="acres" {{ old('land_area_unit', $project ? $project->land_area_unit : 'sq_ft') === 'acres' ? 'selected' : '' }}>Acres</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Optional Info Section -->
            <div id="project-media" class="mb-8 rounded-3xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 4</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Compliance & Summary</h3>
                    <p class="mt-1 text-sm text-slate-600">Legal metadata and internal summary. Premium copy blocks stay in the public builder.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-3">
                    <div>
                        <label for="rera_no" class="block text-sm font-medium text-gray-700 mb-2">RERA Number</label>
                        <input type="text" name="rera_no" id="rera_no" value="{{ old('rera_no', $project ? $project->rera_no : '') }}"
                               class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="possession_date" class="block text-sm font-medium text-gray-700 mb-2">Possession Date</label>
                        <input type="date" name="possession_date" id="possession_date" value="{{ old('possession_date', $project ? $project->possession_date?->format('Y-m-d') : '') }}"
                               class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="rera_qr" class="block text-sm font-medium text-gray-700 mb-2">RERA QR Image</label>
                        <input type="file" name="rera_qr" id="rera_qr" accept="image/jpeg,image/png,image/jpg,image/webp"
                               class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                        @if($project && $project->rera_qr_url)
                            <a href="{{ $project->rera_qr_url }}" target="_blank" class="mt-2 inline-flex text-xs font-semibold text-emerald-700 hover:underline">View current QR</a>
                        @endif
                        <p class="mt-1 text-xs text-gray-500">Optional. JPG, PNG, WebP up to 2MB.</p>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <label for="project_highlights" class="block text-sm font-medium text-gray-700">Project Highlights / USP</label>
                        <button type="button" onclick="generateProjectHighlights()" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                            <i class="fas fa-wand-magic-sparkles mr-1"></i> Generate 5 Highlights
                        </button>
                    </div>
                    <textarea name="project_highlights" id="project_highlights" rows="4"
                              class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('project_highlights', $project ? $project->project_highlights : '') }}</textarea>
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-500">
                        <span>Ideal 5 short points, 8-14 words each. Paste raw notes, then generate.</span>
                        <span><span data-word-count-for="project_highlights">0</span> words</span>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-6">
                    <div class="mb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Public Presentation</p>
                        <h4 class="mt-1 text-base font-semibold text-slate-900">Hero, Branding & Gallery</h4>
                        <p class="mt-1 text-sm text-slate-600">Ye fields direct customer-facing public share page ko control karte hain.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="hero_title" class="block text-sm font-medium text-gray-700 mb-2">Hero Title</label>
                            <input type="text" name="hero_title" id="hero_title" value="{{ old('hero_title', $publicPage?->hero_title) }}" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg" placeholder="Defaults to project name">
                        </div>
                        <div>
                            <label for="hero_subtitle" class="block text-sm font-medium text-gray-700 mb-2">Hero Subtitle</label>
                            <input type="text" name="hero_subtitle" id="hero_subtitle" value="{{ old('hero_subtitle', $publicPage?->hero_subtitle) }}" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg" placeholder="Location or primary offer">
                        </div>
                        <div class="md:col-span-2">
                            <label for="short_intro" class="block text-sm font-medium text-gray-700 mb-2">Public Intro</label>
                            <textarea name="short_intro" id="short_intro" rows="3" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg" placeholder="Leave empty to use Short Overview">{{ old('short_intro', $publicPage?->short_intro) }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label for="featured_badges" class="block text-sm font-medium text-gray-700 mb-2">Featured Badges</label>
                            <input type="text" name="featured_badges" id="featured_badges" value="{{ old('featured_badges', collect($publicPage?->featured_badges ?? [])->join(', ')) }}" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg" placeholder="RERA Approved, Premium Location, Ready to Move">
                        </div>
                        <div>
                            <label for="hero_cover_image" class="block text-sm font-medium text-gray-700 mb-2">Hero Cover Image</label>
                            <input type="file" name="hero_cover_image" id="hero_cover_image" accept="image/jpeg,image/png,image/jpg,image/webp" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                            @if($publicPage?->hero_cover_path)
                                <a href="{{ $publicPage->hero_cover_url }}" target="_blank" class="mt-2 inline-flex text-xs font-semibold text-emerald-700 hover:underline">View current hero</a>
                            @endif
                        </div>
                        <div>
                            <label for="builder_logo_image" class="block text-sm font-medium text-gray-700 mb-2">Public Builder Logo</label>
                            <input type="file" name="builder_logo_image" id="builder_logo_image" accept="image/jpeg,image/png,image/jpg,image/webp" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                            @if($publicPage?->builder_logo_path)
                                <a href="{{ $publicPage->builder_logo_url }}" target="_blank" class="mt-2 inline-flex text-xs font-semibold text-emerald-700 hover:underline">View current builder logo</a>
                            @endif
                        </div>
                        <div class="md:col-span-2">
                            <label for="gallery_images" class="block text-sm font-medium text-gray-700 mb-2">Add Gallery Images</label>
                            <input type="file" name="gallery_images[]" id="gallery_images" multiple accept="image/jpeg,image/png,image/jpg,image/webp" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                            @if($project && $project->publicAssets->where('asset_type', 'gallery_image')->isNotEmpty())
                                <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-5">
                                    @foreach($project->publicAssets->where('asset_type', 'gallery_image')->take(10) as $galleryAsset)
                                        <img src="{{ asset('storage/' . $galleryAsset->file_path) }}" alt="{{ $galleryAsset->title ?: 'Gallery image' }}" class="h-20 w-full rounded-lg border border-slate-200 object-cover">
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unit Types Section -->
            <div class="mb-8 rounded-3xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 5</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Unit Master & Pricing Base</h3>
                    <p class="mt-1 text-sm text-slate-600">Spreadsheet-style pricing base. Public page pricing presentation stays configurable in the builder.</p>
                </div>
                
                <!-- BSP Input Section -->
                @php
                    $existingBSP = $project ? $project->pricingConfig?->bsp_per_sqft : null;
                    $existingRoundingRule = $project ? $project->pricingConfig?->price_rounding_rule : 'none';
                @endphp
                
                <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="bsp_per_sqft" class="block text-sm font-medium text-gray-700 mb-2">
                                BSP (₹ / sq.ft) 
                            </label>
                            <input type="number" step="0.01" id="bsp_per_sqft" name="bsp_per_sqft" 
                                   value="{{ old('bsp_per_sqft', $existingBSP) }}"
                                   onchange="updateAllPrices()"
                                   class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @if($existingBSP)
                                <p class="text-xs text-gray-500 mt-1">Current: ₹{{ number_format($existingBSP, 2) }} / sq.ft</p>
                            @endif
                        </div>
                        <div>
                            <label for="price_rounding_rule" class="block text-sm font-medium text-gray-700 mb-2">Price Rounding Rule</label>
                            <select id="price_rounding_rule" name="price_rounding_rule" 
                                    onchange="updateAllPrices()"
                                    class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="none" {{ old('price_rounding_rule', $existingRoundingRule) === 'none' ? 'selected' : '' }}>None</option>
                                <option value="nearest_1000" {{ old('price_rounding_rule', $existingRoundingRule) === 'nearest_1000' ? 'selected' : '' }}>Nearest 1,000</option>
                                <option value="nearest_10000" {{ old('price_rounding_rule', $existingRoundingRule) === 'nearest_10000' ? 'selected' : '' }}>Nearest 10,000</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            @if(!$existingBSP)
                                <p class="text-sm text-gray-600">Set BSP to calculate prices</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Unit Types Table (Only for non-Flat projects) -->
                <div id="unit-types-section" class="mb-4" style="display: {{ old('residential_sub_type', $project ? $project->residential_sub_type : '') === 'flat' ? 'none' : 'block' }};">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-md font-medium text-gray-700">Unit Types</h4>
                        <button type="button" onclick="addUnitTypeRow()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                            <i class="fas fa-plus mr-1"></i> Add Unit Type
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area (sq.ft)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floor Plan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Action</th>
                                </tr>
                            </thead>
                            <tbody id="unit-types-tbody" class="bg-white divide-y divide-gray-200">
                                @if($project && $project->unitTypes->count() > 0)
                                    @foreach($project->unitTypes as $unitType)
                                        <tr class="unit-type-row" data-unit-type-id="{{ $unitType->id }}">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <input type="text" name="unit_types[{{ $unitType->id }}][unit_type]" 
                                                       value="{{ old('unit_types.'.$unitType->id.'.unit_type', $unitType->unit_type) }}"
                                                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                       placeholder="e.g., 2BHK, 2BHK + Servant">
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <input type="number" step="0.01" name="unit_types[{{ $unitType->id }}][area_sqft]" 
                                                       value="{{ old('unit_types.'.$unitType->id.'.area_sqft', $unitType->area_sqft) }}"
                                                       onchange="calculatePrice(this)"
                                                       oninput="calculatePrice(this)"
                                                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                       placeholder="1200">
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($unitType->floor_plan_image_url)
                                                    <a href="{{ $unitType->floor_plan_image_url }}" target="_blank" class="mb-2 inline-flex text-xs font-semibold text-emerald-700 hover:underline">View current</a>
                                                @endif
                                                <input type="file" name="unit_types[{{ $unitType->id }}][floor_plan_image]" accept="image/jpeg,image/png,image/jpg,image/webp"
                                                       class="w-48 px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg text-sm">
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="unit-price-display text-sm font-medium text-gray-900">
                                                    {{ $unitType->formatted_price ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <button type="button" onclick="removeUnitTypeRow(this)" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <!-- Empty row for new projects -->
                                    <tr class="unit-type-row">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <input type="text" name="unit_types[new_0][unit_type]" 
                                                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                   placeholder="e.g., 2BHK, 2BHK + Servant">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <input type="number" step="0.01" name="unit_types[new_0][area_sqft]" 
                                                   onchange="calculatePrice(this)"
                                                   oninput="calculatePrice(this)"
                                                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                   placeholder="1200">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <input type="file" name="unit_types[new_0][floor_plan_image]" accept="image/jpeg,image/png,image/jpg,image/webp"
                                                   class="w-48 px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg text-sm">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="unit-price-display text-sm font-medium text-gray-900">—</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <button type="button" onclick="removeUnitTypeRow(this)" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Towers Section (Only for Flats) -->
            <div id="towers-section" class="mb-8 rounded-3xl border border-slate-200 bg-white p-5 lg:p-6" style="display: {{ old('residential_sub_type', $project ? $project->residential_sub_type : '') === 'flat' ? 'block' : 'none' }};">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 6</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Tower Configuration</h3>
                    <p class="mt-1 text-sm text-slate-600">Use tower-wise unit rows only for flat inventory. Empty towers will surface as coming soon until detailed rows are added.</p>
                </div>
                
                <div id="towers-container">
                    @if($project && $project->towers->count() > 0)
                        @foreach($project->towers as $towerIndex => $tower)
                            @include('projects.partials.tower-row', ['tower' => $tower, 'towerIndex' => $tower->id, 'project' => $project])
                        @endforeach
                    @endif
                </div>
                
                <button type="button" onclick="addTowerRow()" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                    <i class="fas fa-plus mr-1"></i> Add Tower
                </button>
            </div>

            <!-- Collaterals Section -->
            <div class="mb-8 rounded-3xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 7</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Core Assets</h3>
                    <p class="mt-1 text-sm text-slate-600">Keep brochure, price sheet, floor plans, videos, and legal links here as the base asset register.</p>
                </div>
                
                <div class="mb-4">
                    <button type="button" onclick="addCollateralRow()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm mb-4">
                        <i class="fas fa-plus mr-1"></i> Add Collateral
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Link</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Is Latest</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-20">Action</th>
                            </tr>
                        </thead>
                        <tbody id="collaterals-tbody" class="bg-white divide-y divide-gray-200">
                            @if($project && $project->collaterals->count() > 0)
                                @foreach($project->collaterals as $collateral)
                                    <tr class="collateral-row" data-collateral-id="{{ $collateral->id }}">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <select name="collaterals[{{ $collateral->id }}][category]"
                                                    class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="brochure" {{ $collateral->category === 'brochure' ? 'selected' : '' }}>Brochure</option>
                                                <option value="floor_plans" {{ $collateral->category === 'floor_plans' ? 'selected' : '' }}>Floor Plans</option>
                                                <option value="layout_plan" {{ $collateral->category === 'layout_plan' ? 'selected' : '' }}>Layout Plan</option>
                                                <option value="price_sheet" {{ $collateral->category === 'price_sheet' ? 'selected' : '' }}>Price Sheet</option>
                                                <option value="videos" {{ $collateral->category === 'videos' ? 'selected' : '' }}>Videos</option>
                                                <option value="legal_approvals" {{ $collateral->category === 'legal_approvals' ? 'selected' : '' }}>Legal/RERA/Approvals</option>
                                                <option value="other" {{ $collateral->category === 'other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <input type="text" name="collaterals[{{ $collateral->id }}][title]" 
                                                   value="{{ old('collaterals.'.$collateral->id.'.title', $collateral->title) }}"
                                                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                   placeholder="Collateral Title">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <input type="url" name="collaterals[{{ $collateral->id }}][link]" 
                                                   value="{{ old('collaterals.'.$collateral->id.'.link', $collateral->link) }}"
                                                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                   placeholder="Google Drive or YouTube Link">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <input type="checkbox" name="collaterals[{{ $collateral->id }}][is_latest]" value="1"
                                                   {{ $collateral->is_latest ? 'checked' : '' }}
                                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <button type="button" onclick="removeCollateralRow(this)" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Project Contacts Section -->
            <div id="project-cta" class="mb-8 rounded-3xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Section 8</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Project Contacts</h3>
                    <p class="mt-1 text-sm text-slate-600">These contacts become the operational builder contact source for CRM users and fallback public information.</p>
                </div>
                <div id="builder-contacts-container" class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="contacts_primary" class="block text-sm font-medium text-gray-700 mb-2">Primary Contact</label>
                        <select name="contacts[primary]" id="contacts_primary" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Primary Contact</option>
                        </select>
                    </div>
                    <div>
                        <label for="contacts_secondary" class="block text-sm font-medium text-gray-700 mb-2">Secondary Contact</label>
                        <select name="contacts[secondary]" id="contacts_secondary" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Secondary Contact</option>
                        </select>
                    </div>
                    <div>
                        <label for="contacts_escalation" class="block text-sm font-medium text-gray-700 mb-2">Escalation Contact</label>
                        <select name="contacts[escalation]" id="contacts_escalation" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Escalation Contact</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 border-t border-slate-200 pt-6">
                    <h4 class="text-base font-semibold text-slate-900">Public CTA Controls</h4>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="show_call" value="1" {{ old('show_call', $publicPage?->show_call ?? true) ? 'checked' : '' }}> <span>Show Call</span></label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="show_whatsapp" value="1" {{ old('show_whatsapp', $publicPage?->show_whatsapp ?? true) ? 'checked' : '' }}> <span>Show WhatsApp</span></label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="show_book_visit" value="1" {{ old('show_book_visit', $publicPage?->show_book_visit ?? true) ? 'checked' : '' }}> <span>Show Book Visit</span></label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="show_request_callback" value="1" {{ old('show_request_callback', $publicPage?->show_request_callback ?? false) ? 'checked' : '' }}> <span>Show Callback</span></label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="show_downloads" value="1" {{ old('show_downloads', $publicPage?->show_downloads ?? true) ? 'checked' : '' }}> <span>Show Downloads</span></label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="show_video" value="1" {{ old('show_video', $publicPage?->show_video ?? true) ? 'checked' : '' }}> <span>Show Video</span></label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="show_tour_360" value="1" {{ old('show_tour_360', $publicPage?->show_tour_360 ?? true) ? 'checked' : '' }}> <span>Show 360 Tour</span></label>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Call Number</label><input type="text" name="call_phone" value="{{ old('call_phone', $publicPage?->call_phone) }}" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number</label><input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $publicPage?->whatsapp_number) }}" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Book Visit URL</label><input type="text" name="book_visit_url" value="{{ old('book_visit_url', $publicPage?->book_visit_url) }}" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Callback URL</label><input type="text" name="callback_url" value="{{ old('callback_url', $publicPage?->callback_url) }}" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg"></div>
                    </div>
                </div>
            </div>

            <div class="sticky bottom-4 z-20 -mx-2 mt-10 rounded-3xl border border-slate-200 bg-white/95 px-4 py-4 shadow-lg backdrop-blur">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Save flow</div>
                        <div class="text-sm text-slate-600">Yahin single project builder page par sab sections manage honge. Draft save same page par rahega, preview aur publish yahin se control honge.</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('projects.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </a>
                        <button type="submit" onclick="prepareProjectBuilderSubmit('stay')" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="fas fa-save mr-2"></i> Save Draft
                        </button>
                        <button type="submit" onclick="prepareProjectBuilderSubmit('continue')" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                            <i class="fas fa-arrow-right mr-2"></i> Save &amp; Continue
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@php
$buildersData = $builders->map(function($b) {
    $contacts = $b->activeContacts->map(function($c) {
        return [
            'id' => $c->id,
            'person_name' => $c->person_name,
            'mobile_number' => $c->mobile_number
        ];
    })->values()->toArray();
    
    return [
        'id' => $b->id,
        'name' => $b->name,
        'contacts' => $contacts
    ];
})->values()->toArray();
@endphp

<script>
const builders = @json($buildersData);
const projectBuilderAnchors = ['project-core', 'project-location', 'project-pricing', 'project-media', 'project-cta', 'project-publish'];

function resolveCurrentProjectAnchor() {
    const viewportMark = window.scrollY + (window.innerHeight * 0.35);
    let activeAnchor = 'project-core';

    projectBuilderAnchors.forEach((anchorId) => {
        const section = document.getElementById(anchorId);
        if (!section) {
            return;
        }

        if (section.offsetTop <= viewportMark) {
            activeAnchor = anchorId;
        }
    });

    return activeAnchor;
}

function resolveNextProjectAnchor(currentAnchor) {
    const currentIndex = projectBuilderAnchors.indexOf(currentAnchor);
    if (currentIndex === -1) {
        return 'project-location';
    }

    return projectBuilderAnchors[Math.min(currentIndex + 1, projectBuilderAnchors.length - 1)];
}

function prepareProjectBuilderSubmit(action) {
    const nextActionField = document.getElementById('next_action');
    const nextAnchorField = document.getElementById('next_anchor');
    const currentAnchor = resolveCurrentProjectAnchor();

    if (nextActionField) {
        nextActionField.value = action;
    }

    if (nextAnchorField) {
        nextAnchorField.value = action === 'continue'
            ? resolveNextProjectAnchor(currentAnchor)
            : currentAnchor;
    }
}

function countWords(value) {
    return (value || '').trim().split(/\s+/).filter(Boolean).length;
}

function setupWordCounter(id) {
    const field = document.getElementById(id);
    const target = document.querySelector(`[data-word-count-for="${id}"]`);
    if (!field || !target) {
        return;
    }

    const update = () => {
        target.textContent = countWords(field.value);
    };

    field.addEventListener('input', update);
    update();
}

function generateProjectHighlights() {
    const highlights = document.getElementById('project_highlights');
    const overview = document.getElementById('short_overview');
    if (!highlights) {
        return;
    }

    const source = (highlights.value || (overview ? overview.value : '') || '').trim();
    if (!source) {
        alert('Paste overview or raw project notes first.');
        return;
    }

    const parts = source
        .replace(/\s+/g, ' ')
        .split(/(?<=[.!?])\s+|[,;]\s+/)
        .map((item) => item.trim().replace(/[.!?]+$/, ''))
        .filter((item) => item.length > 20);

    const defaults = [
        'Prime location with strong connectivity and daily convenience.',
        'Thoughtfully planned homes with practical layouts and premium finishes.',
        'Lifestyle amenities designed for comfort, leisure, and community living.',
        'Reliable builder profile with clear project positioning and support.',
        'Balanced pricing, usable carpet area, and strong end-user appeal.'
    ];

    const points = [];
    parts.forEach((part) => {
        if (points.length < 5) {
            points.push(part);
        }
    });
    defaults.forEach((point) => {
        if (points.length < 5) {
            points.push(point);
        }
    });

    highlights.value = points.slice(0, 5).map((point) => `- ${point}`).join('\n');
    highlights.dispatchEvent(new Event('input'));
}

function loadBuilderContacts(builderId) {
    const builder = builders.find(b => b.id == builderId);
    const primarySelect = document.getElementById('contacts_primary');
    const secondarySelect = document.getElementById('contacts_secondary');
    const escalationSelect = document.getElementById('contacts_escalation');
    
    [primarySelect, secondarySelect, escalationSelect].forEach(select => {
        select.innerHTML = '<option value="">Select Contact</option>';
    });
    
    if (builder && builder.contacts) {
        builder.contacts.forEach(contact => {
            const option = `<option value="${contact.id}">${contact.person_name} (${contact.mobile_number})</option>`;
            primarySelect.innerHTML += option;
            secondarySelect.innerHTML += option;
            escalationSelect.innerHTML += option;
        });
    }
}

// Load contacts if builder is pre-selected
@if($project)
    loadBuilderContacts({{ $project->builder_id }});
    @php
        $primaryContact = $project->primaryContact();
        $secondaryContact = $project->secondaryContact();
        $escalationContact = $project->escalationContact();
    @endphp
    @if($primaryContact)
        document.getElementById('contacts_primary').value = {{ $primaryContact->builder_contact_id }};
    @endif
    @if($secondaryContact)
        document.getElementById('contacts_secondary').value = {{ $secondaryContact->builder_contact_id }};
    @endif
    @if($escalationContact)
        document.getElementById('contacts_escalation').value = {{ $escalationContact->builder_contact_id }};
    @endif
@elseif(session('selected_builder_id'))
    // Load contacts for newly created builder
    loadBuilderContacts({{ session('selected_builder_id') }});
@endif
</script>


<!-- Unit Types JavaScript -->
<script>
let unitTypeNewIndex = {{ $project && $project->unitTypes->count() > 0 ? $project->unitTypes->count() : 1 }};

function addUnitTypeRow() {
    const tbody = document.getElementById('unit-types-tbody');
    const row = document.createElement('tr');
    row.className = 'unit-type-row';
    
    const index = 'new_' + unitTypeNewIndex++;
    row.innerHTML = `
        <td class="px-4 py-3 whitespace-nowrap">
            <input type="text" name="unit_types[${index}][unit_type]" 
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="e.g., 2BHK, 2BHK + Servant">
        </td>
        <td class="px-4 py-3 whitespace-nowrap">
            <input type="number" step="0.01" name="unit_types[${index}][area_sqft]" 
                   onchange="calculatePrice(this)"
                   oninput="calculatePrice(this)"
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="1200">
        </td>
        <td class="px-4 py-3 whitespace-nowrap">
            <input type="file" name="unit_types[${index}][floor_plan_image]" accept="image/jpeg,image/png,image/jpg,image/webp"
                   class="w-48 px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg text-sm">
        </td>
        <td class="px-4 py-3 whitespace-nowrap">
            <span class="unit-price-display text-sm font-medium text-gray-900">—</span>
        </td>
        <td class="px-4 py-3 whitespace-nowrap text-center">
            <button type="button" onclick="removeUnitTypeRow(this)" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function removeUnitTypeRow(button) {
    const row = button.closest('tr');
    if (document.querySelectorAll('.unit-type-row').length > 1) {
        row.remove();
    } else {
        row.querySelectorAll('input').forEach(input => input.value = '');
        const price = row.querySelector('.unit-price-display');
        if (price) {
            price.textContent = '-';
        }
    }
}

function calculatePrice(input) {
    const row = input.closest('tr');
    const areaInput = row.querySelector('input[name*="[area_sqft]"]');
    const priceDisplay = row.querySelector('.unit-price-display');
    
    const area = parseFloat(areaInput.value) || 0;
    const bsp = parseFloat(document.getElementById('bsp_per_sqft').value) || 0;
    const roundingRule = document.getElementById('price_rounding_rule').value;
    
    if (!bsp || area <= 0) {
        priceDisplay.textContent = '—';
        return;
    }
    
    let price = area * bsp;
    
    // Apply rounding rule
    if (roundingRule === 'nearest_1000') {
        price = Math.round(price / 1000) * 1000;
    } else if (roundingRule === 'nearest_10000') {
        price = Math.round(price / 10000) * 10000;
    }
    
    // Format price in Indian currency
    priceDisplay.textContent = formatIndianCurrency(price);
}

function updateAllPrices() {
    const rows = document.querySelectorAll('.unit-type-row');
    rows.forEach(row => {
        const areaInput = row.querySelector('input[name*="[area_sqft]"]');
        if (areaInput) {
            calculatePrice(areaInput);
        }
    });
}

function formatIndianCurrency(price) {
    if (price >= 10000000) {
        return '₹' + (price / 10000000).toFixed(2) + ' Cr';
    } else if (price >= 100000) {
        return '₹' + (price / 100000).toFixed(2) + ' L';
    }
    return '₹' + Math.round(price).toLocaleString('en-IN');
}

// Initialize prices on page load
document.addEventListener('DOMContentLoaded', function() {
    setupWordCounter('short_overview');
    setupWordCounter('project_highlights');
    updateAllPrices();
    toggleResidentialSubType();
    toggleTowersSection();
});

// Tower Management Functions
let towerNewIndex = {{ $project && $project->towers->count() > 0 ? $project->towers->max('id') + 1 : 1 }};

function toggleResidentialSubType() {
    const projectType = document.getElementById('project_type').value;
    const container = document.getElementById('residential_sub_type_container');
    if (projectType === 'residential') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
        document.getElementById('residential_sub_type').value = '';
        toggleTowersSection();
    }
}

function toggleTowersSection() {
    const residentialSubType = document.getElementById('residential_sub_type').value;
    const towersSection = document.getElementById('towers-section');
    const unitTypesSection = document.getElementById('unit-types-section');
    
    if (residentialSubType === 'flat') {
        // Show towers, hide direct unit types
        towersSection.style.display = 'block';
        if (unitTypesSection) {
            unitTypesSection.style.display = 'none';
        }
    } else {
        // Hide towers, show direct unit types
        towersSection.style.display = 'none';
        if (unitTypesSection) {
            unitTypesSection.style.display = 'block';
        }
    }
}

function addTowerRow() {
    const container = document.getElementById('towers-container');
    const index = 'new_' + towerNewIndex++;
    
    const towerRow = document.createElement('div');
    towerRow.className = 'tower-row mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50';
    towerRow.innerHTML = `
        <div class="flex justify-between items-center mb-4">
            <h4 class="font-semibold text-gray-800">New Tower</h4>
            <button type="button" onclick="removeTowerRow(this)" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i> Remove Tower
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tower Name</label>
                <input type="text" name="towers[${index}][tower_name]"
                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="e.g., Tower A, Tower 1">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tower Number (Optional)</label>
                <input type="number" name="towers[${index}][tower_number]"
                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="1, 2, 3...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Total Floors</label>
                <input type="number" min="1" name="towers[${index}][floor_count]"
                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="e.g., 24">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Total Units</label>
                <input type="number" min="1" name="towers[${index}][unit_count]"
                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="e.g., 168">
            </div>
        </div>
        <div class="mb-4">
            <div class="flex justify-between items-center mb-3">
                <h5 class="text-md font-medium text-gray-700">Unit Types</h5>
                <button type="button" onclick="addTowerUnitType(this)" class="px-3 py-1 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                    <i class="fas fa-plus mr-1"></i> Add Unit Type
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Area (sq.ft)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Floor Plan</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-20">Action</th>
                        </tr>
                    </thead>
                    <tbody class="tower-unit-types-tbody bg-white divide-y divide-gray-200">
                        <tr class="tower-unit-type-row">
                            <td class="px-4 py-2 text-center text-gray-500" colspan="5">
                                No unit types. Click "Add Unit Type" to add units or this tower will show "Coming Soon".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    container.appendChild(towerRow);
}

function removeTowerRow(button) {
    const row = button.closest('.tower-row');
    row.remove();
}

function addTowerUnitType(button) {
    const towerRow = button.closest('.tower-row');
    const tbody = towerRow.querySelector('.tower-unit-types-tbody');
    
    // Remove "no units" message if exists
    const noUnitsRow = tbody.querySelector('tr td[colspan]');
    if (noUnitsRow) {
        noUnitsRow.closest('tr').remove();
    }
    
    // Get tower index from input name
    const towerInput = towerRow.querySelector('input[name*="[tower_name]"]');
    const towerName = towerInput.name.match(/towers\[([^\]]+)\]/)[1];
    const unitIndex = 'new_' + Date.now();
    
    const row = document.createElement('tr');
    row.className = 'tower-unit-type-row';
    row.innerHTML = `
        <td class="px-4 py-2 whitespace-nowrap">
            <input type="text" name="towers[${towerName}][unit_types][${unitIndex}][unit_type]" 
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="e.g., 2BHK">
        </td>
        <td class="px-4 py-2 whitespace-nowrap">
            <input type="number" step="0.01" name="towers[${towerName}][unit_types][${unitIndex}][area_sqft]" 
                   onchange="calculateTowerUnitPrice(this)"
                   oninput="calculateTowerUnitPrice(this)"
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="1200">
        </td>
        <td class="px-4 py-2 whitespace-nowrap">
            <input type="file" name="towers[${towerName}][unit_types][${unitIndex}][floor_plan_image]" accept="image/jpeg,image/png,image/jpg,image/webp"
                   class="w-48 px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg text-sm">
        </td>
        <td class="px-4 py-2 whitespace-nowrap">
            <span class="tower-unit-price-display text-sm font-medium text-gray-900">—</span>
        </td>
        <td class="px-4 py-2 whitespace-nowrap text-center">
            <button type="button" onclick="removeTowerUnitTypeRow(this)" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function removeTowerUnitTypeRow(button) {
    const row = button.closest('tr');
    const tbody = row.closest('tbody');
    row.remove();
    
    // Show "no units" message if tbody is empty
    if (tbody.querySelectorAll('tr').length === 0) {
        const noUnitsRow = document.createElement('tr');
        noUnitsRow.className = 'tower-unit-type-row';
        noUnitsRow.innerHTML = `
            <td class="px-4 py-2 whitespace-nowrap text-center text-gray-500" colspan="5">
                No unit types. Click "Add Unit Type" to add units or this tower will show "Coming Soon".
            </td>
        `;
        tbody.appendChild(noUnitsRow);
    }
}

function calculateTowerUnitPrice(input) {
    const row = input.closest('tr');
    const areaInput = row.querySelector('input[name*="[area_sqft]"]');
    const priceDisplay = row.querySelector('.tower-unit-price-display');
    
    const area = parseFloat(areaInput.value) || 0;
    const bsp = parseFloat(document.getElementById('bsp_per_sqft').value) || 0;
    const roundingRule = document.getElementById('price_rounding_rule').value;
    
    if (!bsp || area <= 0) {
        priceDisplay.textContent = '—';
        return;
    }
    
    let price = area * bsp;
    
    // Apply rounding rule
    if (roundingRule === 'nearest_1000') {
        price = Math.round(price / 1000) * 1000;
    } else if (roundingRule === 'nearest_10000') {
        price = Math.round(price / 10000) * 10000;
    }
    
    // Format price in Indian currency
    priceDisplay.textContent = formatIndianCurrency(price);
}

// Collaterals JavaScript
let collateralNewIndex = {{ $project && $project->collaterals->count() > 0 ? $project->collaterals->count() : 1 }};

function addCollateralRow() {
    const tbody = document.getElementById('collaterals-tbody');
    const row = document.createElement('tr');
    row.className = 'collateral-row';
    
    const index = 'new_' + collateralNewIndex++;
    row.innerHTML = `
        <td class="px-4 py-3 whitespace-nowrap">
            <select name="collaterals[${index}][category]"
                    class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Select Category</option>
                <option value="brochure">Brochure</option>
                <option value="floor_plans">Floor Plans</option>
                <option value="layout_plan">Layout Plan</option>
                <option value="price_sheet">Price Sheet</option>
                <option value="videos">Videos</option>
                <option value="legal_approvals">Legal/RERA/Approvals</option>
                <option value="other">Other</option>
            </select>
        </td>
        <td class="px-4 py-3 whitespace-nowrap">
            <input type="text" name="collaterals[${index}][title]" 
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="Collateral Title">
        </td>
        <td class="px-4 py-3 whitespace-nowrap">
            <input type="url" name="collaterals[${index}][link]" 
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="Google Drive or YouTube Link">
        </td>
        <td class="px-4 py-3 whitespace-nowrap text-center">
            <input type="checkbox" name="collaterals[${index}][is_latest]" value="1"
                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
        </td>
        <td class="px-4 py-3 whitespace-nowrap text-center">
            <button type="button" onclick="removeCollateralRow(this)" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function removeCollateralRow(button) {
    const row = button.closest('tr');
    row.remove();
}

// Tower Copy Units JavaScript
function toggleCopyUnitsDropdown(button) {
    const dropdown = button.nextElementSibling;
    const isHidden = dropdown.classList.contains('hidden');
    
    // Close all other dropdowns
    document.querySelectorAll('.copy-units-dropdown').forEach(d => {
        if (d !== dropdown) {
            d.classList.add('hidden');
        }
    });
    
    if (isHidden) {
        dropdown.classList.remove('hidden');
        populateCopyUnitsDropdown(button);
    } else {
        dropdown.classList.add('hidden');
    }
}

function populateCopyUnitsDropdown(button) {
    const dropdown = button.nextElementSibling;
    const optionsDiv = dropdown.querySelector('.copy-units-options');
    const currentTowerRow = button.closest('.tower-row');
    const currentTowerInput = currentTowerRow.querySelector('input[name*="[tower_name]"]');
    const currentTowerName = currentTowerInput ? currentTowerInput.name.match(/towers\[([^\]]+)\]/)[1] : null;
    
    // Get all towers except current one
    const allTowerRows = document.querySelectorAll('.tower-row');
    optionsDiv.innerHTML = '';
    
    if (allTowerRows.length <= 1) {
        optionsDiv.innerHTML = '<div class="p-2 text-xs text-gray-500">No other towers available</div>';
        return;
    }
    
    allTowerRows.forEach(towerRow => {
        const towerInput = towerRow.querySelector('input[name*="[tower_name]"]');
        if (!towerInput) return;
        
        const towerName = towerInput.name.match(/towers\[([^\]]+)\]/)[1];
        if (towerName === currentTowerName) return; // Skip current tower
        
        const towerDisplayName = towerInput.value || `Tower ${towerName}`;
        const unitTypesTbody = towerRow.querySelector('.tower-unit-types-tbody');
        const unitRows = unitTypesTbody ? unitTypesTbody.querySelectorAll('.tower-unit-type-row:not([colspan])') : [];
        
        if (unitRows.length === 0) {
            return; // Skip towers with no units
        }
        
        const option = document.createElement('div');
        option.className = 'p-2 hover:bg-gray-100 cursor-pointer';
        option.innerHTML = `<div class="text-sm font-medium">${towerDisplayName}</div><div class="text-xs text-gray-500">${unitRows.length} unit(s)</div>`;
        option.onclick = () => {
            copyUnitsFromTower(towerName, currentTowerName);
            dropdown.classList.add('hidden');
        };
        optionsDiv.appendChild(option);
    });
    
    if (optionsDiv.children.length === 0) {
        optionsDiv.innerHTML = '<div class="p-2 text-xs text-gray-500">No towers with units available</div>';
    }
}

function copyUnitsFromTower(sourceTowerName, targetTowerName) {
    // Find source tower row
    const sourceTowerRow = Array.from(document.querySelectorAll('.tower-row')).find(row => {
        const input = row.querySelector('input[name*="[tower_name]"]');
        return input && input.name.match(/towers\[([^\]]+)\]/)[1] === sourceTowerName;
    });
    
    if (!sourceTowerRow) {
        alert('Source tower not found');
        return;
    }
    
    // Find target tower row
    const targetTowerRow = Array.from(document.querySelectorAll('.tower-row')).find(row => {
        const input = row.querySelector('input[name*="[tower_name]"]');
        return input && input.name.match(/towers\[([^\]]+)\]/)[1] === targetTowerName;
    });
    
    if (!targetTowerRow) {
        alert('Target tower not found');
        return;
    }
    
    // Get source unit types
    const sourceTbody = sourceTowerRow.querySelector('.tower-unit-types-tbody');
    const sourceUnitRows = sourceTbody ? sourceTbody.querySelectorAll('.tower-unit-type-row:not([colspan])') : [];
    
    if (sourceUnitRows.length === 0) {
        alert('Source tower has no units to copy');
        return;
    }
    
    // Get target tbody
    const targetTbody = targetTowerRow.querySelector('.tower-unit-types-tbody');
    if (!targetTbody) {
        alert('Target tower tbody not found');
        return;
    }
    
    // Remove "no units" message if exists
    const noUnitsRow = targetTbody.querySelector('tr td[colspan]');
    if (noUnitsRow) {
        noUnitsRow.closest('tr').remove();
    }
    
    // Copy each unit
    sourceUnitRows.forEach(sourceRow => {
        const unitTypeInput = sourceRow.querySelector('input[name*="[unit_type]"]');
        const areaInput = sourceRow.querySelector('input[name*="[area_sqft]"]');
        
        if (!unitTypeInput || !areaInput) return;
        
        const unitType = unitTypeInput.value;
        const area = areaInput.value;
        
        // Create new row in target tower
        const unitIndex = 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const newRow = document.createElement('tr');
        newRow.className = 'tower-unit-type-row';
        newRow.innerHTML = `
            <td class="px-4 py-2 whitespace-nowrap">
                <input type="text" name="towers[${targetTowerName}][unit_types][${unitIndex}][unit_type]" 
                       value="${unitType}"
                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="e.g., 2BHK">
            </td>
            <td class="px-4 py-2 whitespace-nowrap">
                <input type="number" step="0.01" name="towers[${targetTowerName}][unit_types][${unitIndex}][area_sqft]" 
                       value="${area}"
                       onchange="calculateTowerUnitPrice(this)"
                       oninput="calculateTowerUnitPrice(this)"
                       class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="1200">
            </td>
            <td class="px-4 py-2 whitespace-nowrap">
                <input type="file" name="towers[${targetTowerName}][unit_types][${unitIndex}][floor_plan_image]" accept="image/jpeg,image/png,image/jpg,image/webp"
                       class="w-48 px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg text-sm">
            </td>
            <td class="px-4 py-2 whitespace-nowrap">
                <span class="tower-unit-price-display text-sm font-medium text-gray-900">—</span>
            </td>
            <td class="px-4 py-2 whitespace-nowrap text-center">
                <button type="button" onclick="removeTowerUnitTypeRow(this)" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        targetTbody.appendChild(newRow);
        
        // Calculate price if BSP is set
        const areaInputNew = newRow.querySelector('input[name*="[area_sqft]"]');
        if (areaInputNew) {
            calculateTowerUnitPrice(areaInputNew);
        }
    });
    
    // Show success message
    const successMsg = document.createElement('div');
    successMsg.className = 'mt-2 p-2 bg-green-100 text-green-800 rounded text-sm';
    successMsg.textContent = `Copied ${sourceUnitRows.length} unit(s) successfully!`;
    targetTowerRow.appendChild(successMsg);
    setTimeout(() => successMsg.remove(), 3000);
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.copy-units-dropdown') && !event.target.closest('button[onclick*="toggleCopyUnitsDropdown"]')) {
        document.querySelectorAll('.copy-units-dropdown').forEach(d => d.classList.add('hidden'));
    }
});
</script>
@endsection
