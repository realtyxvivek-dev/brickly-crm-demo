@extends('layouts.app')

@section('title', 'Lead Form Builder - Admin')
@section('page-title', 'Lead Form Builder')
@section('page-subtitle', 'Manage lead requirement form fields and levels')

@section('header-actions')
<a href="{{ route('admin.lead-form-builder.create') }}" class="btn btn-brand-gradient" style="color: white; text-decoration: none;">
    <i class="fas fa-plus" style="margin-right: 5px;"></i> Add New Field
</a>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    @if(session('success'))
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Fields by Level -->
    <div class="space-y-6">
        @foreach(['telecaller' => 'Sales Executive Level', 'sales_executive' => 'Sales Executive Level', 'sales_manager' => 'Senior Manager Level'] as $level => $levelLabel)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                    {{ $levelLabel }} Fields
                </h2>
                
                @php
                    $levelFields = $fieldsByLevel[$level] ?? collect();
                @endphp

                @if($levelFields->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field Key</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($levelFields->sortBy('display_order') as $field)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $field->display_order }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-600">{{ $field->field_key }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $field->field_label }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">{{ $field->field_type }}</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            @if($field->is_required)
                                                <span class="text-red-600 font-semibold">Yes</span>
                                            @else
                                                <span class="text-gray-400">No</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            @if($field->is_active)
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('admin.lead-form-builder.edit', $field) }}" class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.lead-form-builder.toggle-active', $field) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                                        <i class="fas fa-{{ $field->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.lead-form-builder.destroy', $field) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this field? This action cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">No fields configured for {{ $levelLabel }} yet.</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
