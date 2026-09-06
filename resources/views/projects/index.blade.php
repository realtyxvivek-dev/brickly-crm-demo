@extends('layouts.app')

@section('title', 'Projects - ' . brand_name())
@section('page-title', 'Projects')

@push('styles')
<style>
    .projects-toolbar {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .projects-toolbar__group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid rgba(32, 90, 68, 0.12);
        box-shadow: 0 10px 24px rgba(6, 58, 28, 0.05);
    }
    .projects-toolbar__label {
        padding: 0 6px 0 2px;
        color: #6b7280;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .projects-toolbar__btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-height: 46px;
        padding: 0 16px;
        border-radius: 14px;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .projects-toolbar__btn i {
        font-size: 13px;
        opacity: 0.9;
    }
    .projects-toolbar__btn--muted {
        background: #f8faf8;
        color: #205A44;
        border: 1px solid rgba(32, 90, 68, 0.14);
    }
    .projects-toolbar__btn--muted:hover {
        background: #eff6f1;
        border-color: rgba(32, 90, 68, 0.24);
        transform: translateY(-1px);
    }
    .projects-toolbar__btn--primary {
        background: linear-gradient(135deg, #0b4b25 0%, #205A44 100%);
        color: #fff;
        box-shadow: 0 12px 22px rgba(32, 90, 68, 0.18);
    }
    .projects-toolbar__btn--primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 28px rgba(32, 90, 68, 0.24);
    }
    .projects-toolbar__btn--ghost {
        background: #fff;
        color: #4b5563;
        border: 1px solid #d9e1db;
    }
    .projects-toolbar__btn--ghost:hover {
        background: #f9fafb;
        border-color: #c7d4cb;
    }
    @media (max-width: 1024px) {
        .projects-toolbar {
            width: 100%;
        }
        .projects-toolbar__group {
            width: 100%;
            flex-wrap: wrap;
        }
    }
</style>
@endpush

@section('header-actions')
    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
        <div class="projects-toolbar">
            <div class="projects-toolbar__group">
                <span class="projects-toolbar__label">Imports</span>
                <a href="{{ route('projects.import.template') }}" class="projects-toolbar__btn projects-toolbar__btn--muted">
                    <i class="fas fa-file-arrow-down"></i>
                    <span>Sample Excel</span>
                </a>
                <a href="{{ route('projects.import-url.index') }}" class="projects-toolbar__btn projects-toolbar__btn--muted">
                    <i class="fas fa-link"></i>
                    <span>Import URL</span>
                </a>
                <a href="{{ route('projects.import.index') }}" class="projects-toolbar__btn projects-toolbar__btn--muted">
                    <i class="fas fa-file-excel"></i>
                    <span>Import Excel</span>
                </a>
            </div>

            <div class="projects-toolbar__group">
                <span class="projects-toolbar__label">Create</span>
                <a href="{{ route('projects.create') }}" class="projects-toolbar__btn projects-toolbar__btn--primary">
                    <i class="fas fa-plus"></i>
                    <span>Create Project</span>
                </a>
            </div>
        </div>
    @endif
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
        <div class="mb-6 rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">Projects</p>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-900">Project Library</h2>
                    <p class="mt-1 text-sm text-slate-600">Create core project setup first, then complete the public page builder before publishing.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('projects.create') }}" class="inline-flex items-center rounded-xl bg-[#064e3b] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#053f30]">
                        <i class="fas fa-plus mr-2"></i>
                        Create Project
                    </a>
                </div>
            </div>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Builder</label>
                <select name="builder_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Builders</option>
                    @foreach($builders as $builder)
                        <option value="{{ $builder->id }}" {{ request('builder_id') == $builder->id ? 'selected' : '' }}>
                            {{ $builder->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Project Type</label>
                <select name="project_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Types</option>
                    <option value="residential" {{ request('project_type') == 'residential' ? 'selected' : '' }}>Residential</option>
                    <option value="commercial" {{ request('project_type') == 'commercial' ? 'selected' : '' }}>Commercial</option>
                    <option value="mixed" {{ request('project_type') == 'mixed' ? 'selected' : '' }}>Mixed</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="project_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Status</option>
                    <option value="prelaunch" {{ request('project_status') == 'prelaunch' ? 'selected' : '' }}>Prelaunch</option>
                    <option value="under_construction" {{ request('project_status') == 'under_construction' ? 'selected' : '' }}>Under Construction</option>
                    <option value="ready" {{ request('project_status') == 'ready' ? 'selected' : '' }}>Ready</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Projects Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($projects as $project)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200">
                <!-- Card Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ $project->name }}</h3>
                            @if($project->builder)
                                <p class="text-sm text-gray-500">
                                    <i class="fas fa-building mr-1"></i>
                                    {{ $project->builder->name }}
                                </p>
                            @endif
                        </div>
                        @if($project->builder && $project->builder->logo)
                            <img src="{{ $project->builder->logo_url }}" alt="{{ $project->builder->name }}" class="h-12 w-12 rounded-lg object-cover ml-3">
                        @endif
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-6">
                    <!-- Location -->
                    @if($project->city || $project->area)
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt mr-2 text-indigo-500"></i>
                                @if($project->city && $project->area)
                                    {{ $project->city }}, {{ $project->area }}
                                @else
                                    {{ $project->city ?: $project->area }}
                                @endif
                            </p>
                        </div>
                    @endif

                    <!-- Project Details -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Type</p>
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                {{ $project->project_type ? ucfirst($project->project_type) : 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Status</p>
                            <span class="px-2 py-1 text-xs rounded-full {{ $project->project_status === 'ready' ? 'bg-green-100 text-green-800' : ($project->project_status === 'under_construction' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                {{ $project->project_status ? ucfirst(str_replace('_', ' ', $project->project_status)) : 'N/A' }}
                            </span>
                        </div>
                    </div>

                    <!-- Starting Price -->
                    @php
                        $startingUnit = $project->startingFromUnit();
                    @endphp
                    @if($startingUnit && $startingUnit->calculated_price)
                        <div class="mb-4 p-3 bg-indigo-50 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">Starting From</p>
                            <p class="text-lg font-bold text-indigo-600">{{ $startingUnit->formatted_price }}</p>
                        </div>
                    @endif

                    <!-- Project Highlights (if available) -->
                    @if($project->project_highlights)
                        <div class="mb-4">
                            <p class="text-xs text-gray-500 mb-1">Highlights</p>
                            <p class="text-sm text-gray-700 line-clamp-2">{{ Str::limit($project->project_highlights, 100) }}</p>
                        </div>
                    @endif
                </div>

                <!-- Card Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4 flex-wrap">
                        <a href="{{ route('projects.show', $project) }}" class="text-indigo-600 hover:text-indigo-800 font-medium text-sm">
                            View Details <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                        @php
                            $activeShareLink = $project->shareLinks->first(function ($shareLink) {
                                return $shareLink->status === 'active';
                            });
                            $canOpenPublicView = $project->publicPage && in_array($project->publicPage->status, ['published', 'draft', 'hidden'], true) && $activeShareLink;
                        @endphp
                        @if($canOpenPublicView)
                            <a href="{{ route('projects.public-share.show', $activeShareLink->token) }}"
                               target="_blank"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-[#205A44]/15 bg-white text-[#205A44] hover:bg-[#F3F7F4] font-medium text-sm">
                                <i class="fas fa-globe"></i>
                                <span>Public View</span>
                            </a>
                        @endif
                    </div>
                    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('projects.edit', $project) }}" class="text-[#205A44] hover:text-[#063A1C]" title="Open Project Builder">
                                <i class="fas fa-wand-magic-sparkles"></i>
                            </a>
                            <a href="{{ route('projects.edit', $project) }}" class="text-gray-400 hover:text-gray-700" title="Edit Project">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('projects.destroy', $project) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                    <i class="fas fa-project-diagram text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500 text-lg mb-2">No projects found</p>
                    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                        <div class="mt-4 flex items-center justify-center gap-3">
                            <a href="{{ route('projects.import-url.index') }}" class="inline-block px-6 py-2 bg-white border border-[#205A44]/20 text-[#205A44] rounded-lg hover:bg-[#F3F7F4]">
                                Import from URL
                            </a>
                            <a href="{{ route('projects.import.index') }}" class="inline-block px-6 py-2 bg-white border border-[#205A44]/20 text-[#205A44] rounded-lg hover:bg-[#F3F7F4]">
                                Import Project Excel
                            </a>
                            <a href="{{ route('projects.create') }}" class="inline-block px-6 py-2 bg-[#205A44] text-white rounded-lg hover:bg-[#063A1C]">
                                Create Your First Project
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $projects->links() }}
    </div>
@endsection
