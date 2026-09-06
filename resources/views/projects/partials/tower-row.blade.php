<div class="tower-row mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50">
    <div class="flex justify-between items-center mb-4">
        <h4 class="font-semibold text-gray-800">Tower {{ $towerIndex }}</h4>
        <button type="button" onclick="removeTowerRow(this)" class="text-red-600 hover:text-red-800">
            <i class="fas fa-trash"></i> Remove Tower
        </button>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tower Name</label>
            <input type="text" name="towers[{{ $towerIndex }}][tower_name]" 
                   value="{{ old('towers.'.$towerIndex.'.tower_name', $tower ? $tower->tower_name : '') }}"
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="e.g., Tower A, Tower 1">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tower Number (Optional)</label>
            <input type="number" name="towers[{{ $towerIndex }}][tower_number]" 
                   value="{{ old('towers.'.$towerIndex.'.tower_number', $tower ? $tower->tower_number : '') }}"
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="1, 2, 3...">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Total Floors</label>
            <input type="number" min="1" name="towers[{{ $towerIndex }}][floor_count]" 
                   value="{{ old('towers.'.$towerIndex.'.floor_count', $tower ? $tower->floor_count : '') }}"
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="e.g., 24">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Total Units</label>
            <input type="number" min="1" name="towers[{{ $towerIndex }}][unit_count]" 
                   value="{{ old('towers.'.$towerIndex.'.unit_count', $tower ? $tower->unit_count : '') }}"
                   class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="e.g., 168">
        </div>
    </div>
    
    <!-- Unit Types for this Tower -->
    <div class="mb-4">
        <div class="flex justify-between items-center mb-3">
            <h5 class="text-md font-medium text-gray-700">Unit Types</h5>
            <div class="flex gap-2">
                <div class="relative">
                    <button type="button" onclick="toggleCopyUnitsDropdown(this)" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                        <i class="fas fa-copy mr-1"></i> Copy Units
                    </button>
                    <div class="copy-units-dropdown hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-10 min-w-48" style="max-height: 200px; overflow-y: auto;">
                        <div class="p-2 text-xs text-gray-500 border-b">Select source tower:</div>
                        <div class="copy-units-options"></div>
                    </div>
                </div>
                <button type="button" onclick="addTowerUnitType(this)" class="px-3 py-1 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                    <i class="fas fa-plus mr-1"></i> Add Unit Type
                </button>
            </div>
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
                    @if($tower && $tower->unitTypes->count() > 0)
                        @foreach($tower->unitTypes as $unitType)
                            <tr class="tower-unit-type-row">
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <input type="text" name="towers[{{ $towerIndex }}][unit_types][{{ $unitType->id }}][unit_type]" 
                                           value="{{ old('towers.'.$towerIndex.'.unit_types.'.$unitType->id.'.unit_type', $unitType->unit_type) }}"
                                           class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                           placeholder="e.g., 2BHK">
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <input type="number" step="0.01" name="towers[{{ $towerIndex }}][unit_types][{{ $unitType->id }}][area_sqft]" 
                                           value="{{ old('towers.'.$towerIndex.'.unit_types.'.$unitType->id.'.area_sqft', $unitType->area_sqft) }}"
                                           onchange="calculateTowerUnitPrice(this)"
                                           oninput="calculateTowerUnitPrice(this)"
                                           class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                           placeholder="1200">
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if($unitType->floor_plan_image_url)
                                        <a href="{{ $unitType->floor_plan_image_url }}" target="_blank" class="mb-2 inline-flex text-xs font-semibold text-emerald-700 hover:underline">View current</a>
                                    @endif
                                    <input type="file" name="towers[{{ $towerIndex }}][unit_types][{{ $unitType->id }}][floor_plan_image]" accept="image/jpeg,image/png,image/jpg,image/webp"
                                           class="w-48 px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg text-sm">
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span class="tower-unit-price-display text-sm font-medium text-gray-900">
                                        {{ $unitType->formatted_price ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-center">
                                    <button type="button" onclick="removeTowerUnitTypeRow(this)" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr class="tower-unit-type-row">
                            <td class="px-4 py-2 text-center text-gray-500" colspan="5">
                                No unit types. Click "Add Unit Type" to add units or this tower will show "Coming Soon".
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
