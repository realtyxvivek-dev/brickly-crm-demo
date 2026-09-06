@extends('layouts.app')

@section('title', $project->name . ' - ' . brand_name())
@section('page-title', $project->name)

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Sticky Action Bar -->
    <div class="sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm mb-6">
        <div class="flex justify-between items-center p-4">
            <div class="flex items-center space-x-4">
                @php
                    $primaryContactObj = $project->primaryContact();
                    $primaryContact = $primaryContactObj?->builderContact;
                    $secondaryContactObj = $project->secondaryContact();
                    $escalationContactObj = $project->escalationContact();
                @endphp
                @if($primaryContact)
                    <a href="{{ \App\Helpers\ContactHelper::getCallUrl($primaryContact->mobile_number) }}" 
                       class="flex items-center space-x-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-phone"></i>
                        <span>Call Builder</span>
                    </a>
                    <a href="{{ \App\Helpers\ContactHelper::getWhatsAppUrl($primaryContact->getEffectiveWhatsAppNumber()) }}" 
                       target="_blank"
                       class="flex items-center space-x-2 px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp Builder</span>
                    </a>
                    @if($secondaryContactObj || $escalationContactObj)
                        <div class="relative">
                            <button id="more-contacts-btn" class="flex items-center space-x-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                <span>More Contacts</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="more-contacts-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                @if($secondaryContactObj)
                                    @php $sec = $secondaryContactObj->builderContact; @endphp
                                    <a href="{{ \App\Helpers\ContactHelper::getCallUrl($sec->mobile_number) }}" class="block px-4 py-2 text-gray-900 hover:bg-gray-100">
                                        Call Secondary
                                    </a>
                                    <a href="{{ \App\Helpers\ContactHelper::getWhatsAppUrl($sec->getEffectiveWhatsAppNumber()) }}" target="_blank" class="block px-4 py-2 text-gray-900 hover:bg-gray-100">
                                        WhatsApp Secondary
                                    </a>
                                @endif
                                @if($escalationContactObj)
                                    @php $esc = $escalationContactObj->builderContact; @endphp
                                    <a href="{{ \App\Helpers\ContactHelper::getCallUrl($esc->mobile_number) }}" class="block px-4 py-2 text-gray-900 hover:bg-gray-100">
                                        Call Escalation
                                    </a>
                                    <a href="{{ \App\Helpers\ContactHelper::getWhatsAppUrl($esc->getEffectiveWhatsAppNumber()) }}" target="_blank" class="block px-4 py-2 text-gray-900 hover:bg-gray-100">
                                        WhatsApp Escalation
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif
            </div>
            @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                <div class="flex items-center gap-3">
                    <a href="{{ route('projects.edit', $project) }}" class="px-4 py-2 bg-[#205A44] text-white rounded-lg hover:bg-[#063A1C]">
                        Open Project Builder
                    </a>
                    <a href="{{ route('projects.edit', $project) }}#project-publish" class="px-4 py-2 bg-white border border-[#205A44]/20 text-[#205A44] rounded-lg hover:bg-[#F3F7F4]">
                        Publish Controls
                    </a>
                </div>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button onclick="showTab('overview')" id="tab-overview" class="tab-button active px-6 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">
                    Overview
                </button>
                <button onclick="showTab('pricing')" id="tab-pricing" class="tab-button px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Unit Types & Pricing
                </button>
                <button onclick="showTab('collaterals')" id="tab-collaterals" class="tab-button px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Collaterals
                </button>
            </nav>
        </div>

        <!-- Overview Tab -->
        <div id="content-overview" class="tab-content p-6">
            <!-- Logo and Overview -->
            @if($project->logo || $project->short_overview)
                <div class="mb-6">
                    <div class="flex items-start gap-6">
                        @if($project->logo)
                            <div class="flex-shrink-0">
                                <img src="{{ $project->logo_url }}" alt="{{ $project->name }}" class="h-32 w-32 rounded-lg object-cover border border-gray-200">
                            </div>
                        @endif
                        @if($project->short_overview)
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Overview</h3>
                                <p class="text-gray-700 leading-relaxed">{{ $project->short_overview }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-1">Builder</h4>
                    <p class="text-lg font-semibold text-gray-900">{{ $project->builder->name }}</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-1">Location</h4>
                    <p class="text-lg font-semibold text-gray-900">{{ $project->formatted_location }}</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-1">Project Type</h4>
                    <p class="text-lg font-semibold text-gray-900">
                        {{ ucfirst($project->project_type) }}
                        @if($project->project_type === 'residential' && $project->residential_sub_type)
                            <span class="text-sm text-gray-600">({{ ucfirst($project->residential_sub_type) }})</span>
                        @endif
                    </p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-1">Status</h4>
                    <p class="text-lg font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $project->project_status)) }}</p>
                </div>
                @if($project->land_area)
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 mb-1">Project Size</h4>
                        <p class="text-lg font-semibold text-gray-900">{{ $project->formatted_land_area }}</p>
                    </div>
                @endif
                @if($project->rera_no)
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 mb-1">RERA Number</h4>
                        <p class="text-lg font-semibold text-gray-900">{{ $project->rera_no }}</p>
                    </div>
                @endif
            </div>

            @if($project->project_highlights)
                <div class="mb-6">
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Project Highlights / USP</h4>
                    <p class="text-gray-700 whitespace-pre-line">{{ $project->project_highlights }}</p>
                </div>
            @endif

            @if($project->configuration_summary && count($project->configuration_summary) > 0)
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Configuration Summary</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($project->configuration_summary as $config)
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm">{{ ucfirst($config) }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Pricing Tab -->
        <div id="content-pricing" class="tab-content hidden p-6">
            @if($project->pricingConfig)
                <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <h3 class="text-lg font-semibold mb-2 text-gray-900">BSP (Base Selling Price)</h3>
                    <p class="text-xl font-semibold text-gray-900">{{ $project->pricingConfig->formatted_bsp }}</p>
                    @if($project->pricingConfig->price_rounding_rule && $project->pricingConfig->price_rounding_rule !== 'none')
                        <p class="text-sm text-gray-600 mt-1">Price Rounding: {{ ucfirst(str_replace('_', ' ', $project->pricingConfig->price_rounding_rule)) }}</p>
                    @endif
                </div>
            @endif

            <!-- Towers Section (ONLY for Flats) -->
            @if($project->residential_sub_type === 'flat')
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900">Towers</h3>
                    @if($project->towers->count() > 0)
                    <div class="space-y-6">
                        @foreach($project->towers as $tower)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-md font-semibold text-gray-800">
                                        {{ $tower->tower_name }}
                                        @if($tower->tower_number)
                                            <span class="text-sm text-gray-600">({{ $tower->tower_number }})</span>
                                        @endif
                                    </h4>
                                    @if($tower->isComingSoon)
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">Coming Soon</span>
                                    @endif
                                </div>
                                
                                @if($tower->unitTypes->count() > 0)
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Type</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Area (sq.ft)</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @foreach($tower->unitTypes as $unitType)
                                                    <tr>
                                                        <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $unitType->display_label }}</td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ number_format($unitType->area_sqft, 0) }}</td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-gray-900">
                                                            {{ $unitType->formatted_price ?? '—' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-gray-500 text-center py-4">No unit types available</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No towers added yet.</p>
                    @endif
                </div>
            @endif

            <!-- Unit Types Table (for Plots/Villas or direct project units - NOT for Flats) -->
            @if($project->residential_sub_type !== 'flat' && $project->unitTypes->count() > 0)
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Unit Types</h3>
                    </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area (sq.ft)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Starting From</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($project->unitTypes as $unitType)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900">{{ $unitType->display_label }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900">{{ number_format($unitType->area_sqft, 0) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900">
                                        @if($unitType->calculated_price)
                                            {{ $unitType->formatted_price }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($unitType->is_starting_from)
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Starting From</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No unit types added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <!-- Collaterals Tab -->
        <div id="content-collaterals" class="tab-content hidden p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold mb-4 text-gray-900">Collaterals</h3>
            </div>

            <!-- Collateral Buttons Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="collateral-buttons">
                @php
                    $categories = ['brochure', 'floor_plans', 'layout_plan', 'videos', 'price_sheet', 'legal_approvals', 'other'];
                    $icons = ['📄', '📐', '🗺', '🎥', '💰', '📁', '📋'];
                @endphp
                @foreach($categories as $index => $category)
                    @php
                        $categoryCollaterals = $project->collaterals->where('category', $category);
                        $count = $categoryCollaterals->count();
                        $hasLatest = $categoryCollaterals->where('is_latest', true)->count() > 0;
                    @endphp
                    @if($count > 0)
                        <button onclick="openCollateral('{{ $category }}')" 
                                class="collateral-btn p-4 border-2 rounded-lg text-left hover:bg-gray-50 transition {{ $hasLatest ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-2xl mb-1">{{ $icons[$index] }}</div>
                                    <div class="font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $category)) }}</div>
                                    @if($count > 1)
                                        <div class="text-sm text-gray-500">{{ $count }} items</div>
                                    @endif
                                </div>
                                @if($hasLatest)
                                    <span class="px-2 py-1 bg-indigo-600 text-white rounded text-xs">Latest</span>
                                @endif
                            </div>
                        </button>
                        <!-- Hidden data for this category -->
                        <div id="collateral-data-{{ $category }}" class="hidden">
                            @foreach($categoryCollaterals as $collateral)
                                <div data-link="{{ $collateral->link }}" data-title="{{ $collateral->title }}"></div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            @if($project->collaterals->count() === 0)
                <div class="text-center py-12 text-gray-500">
                    <i class="fas fa-folder-open text-4xl mb-4"></i>
                    <p>No collaterals added yet.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'text-indigo-600', 'border-indigo-600');
        btn.classList.add('text-gray-500');
    });

    // Show selected tab
    document.getElementById('content-' + tabName).classList.remove('hidden');
    const btn = document.getElementById('tab-' + tabName);
    btn.classList.add('active', 'text-indigo-600', 'border-indigo-600');
    btn.classList.remove('text-gray-500');
}

function openCollateral(category) {
    const dataDiv = document.getElementById('collateral-data-' + category);
    if (!dataDiv) return;
    
    const items = dataDiv.querySelectorAll('div[data-link]');
    
    if (items.length === 1) {
        // Single item - open directly
        window.open(items[0].getAttribute('data-link'), '_blank');
    } else {
        // Multiple items - show modal or dropdown
        let links = '';
        items.forEach(item => {
            links += `<a href="${item.getAttribute('data-link')}" target="_blank" class="block px-4 py-2 hover:bg-gray-100">${item.getAttribute('data-title')}</a>`;
        });
        // Simple modal
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Select Collateral</h3>
                <div class="space-y-2">${links}</div>
                <button onclick="this.closest('.fixed').remove()" class="mt-4 px-4 py-2 bg-gray-200 rounded">Close</button>
            </div>
        `;
        document.body.appendChild(modal);
    }
}

// More contacts dropdown
document.getElementById('more-contacts-btn')?.addEventListener('click', function() {
    document.getElementById('more-contacts-menu').classList.toggle('hidden');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('more-contacts-menu');
    const btn = document.getElementById('more-contacts-btn');
    if (menu && btn && !menu.contains(event.target) && !btn.contains(event.target)) {
        menu.classList.add('hidden');
    }
});
</script>
@endsection
