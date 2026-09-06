@extends('layouts.app')

@section('title', 'Form Integrations - ' . brand_name())
@section('page-title', 'Form Integrations')

@section('header-actions')
    <a href="{{ route('integrations.form-integration.create') }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-plus mr-2"></i>
        Add New Integration
    </a>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Configured Integrations</h2>
        
        @if($configs->isEmpty())
            <div class="text-center py-12">
                <i class="fas fa-inbox text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 mb-4">No form integrations configured yet.</p>
                <a href="{{ route('integrations.form-integration.create') }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium inline-block">
                    <i class="fas fa-plus mr-2"></i>
                    Add Your First Integration
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sheet Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sheet Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mappings</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($configs as $config)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $typeLabels = [
                                            'meta_facebook' => 'Meta/Facebook',
                                            'google_forms' => 'Google Forms',
                                            'custom' => 'Custom'
                                        ];
                                        $typeColors = [
                                            'meta_facebook' => 'bg-blue-100 text-blue-800',
                                            'google_forms' => 'bg-green-100 text-green-800',
                                            'custom' => 'bg-gray-100 text-gray-800'
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $typeColors[$config->sheet_type] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $typeLabels[$config->sheet_type] ?? 'Custom' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $config->sheet_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($config->is_active)
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $config->columnMappings->count() }} fields mapped
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="testIntegration({{ $config->id }})" class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-vial"></i> Test
                                        </button>
                                        <button onclick="toggleIntegration({{ $config->id }})" class="text-yellow-600 hover:text-yellow-900">
                                            <i class="fas fa-toggle-{{ $config->is_active ? 'on' : 'off' }}"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function testIntegration(id) {
    if (!confirm('This will send a test lead to CRM. Continue?')) {
        return;
    }
    
    fetch(`/integrations/form-integration/test/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test successful! Lead ID: ' + (data.lead_id || 'N/A'));
        } else {
            alert('Test failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Test failed: ' + error.message);
    });
}

function toggleIntegration(id) {
    fetch(`/integrations/form-integration/toggle/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to toggle integration');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to toggle integration');
    });
}
</script>
@endpush
@endsection
