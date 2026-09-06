@extends('sales-manager.layout')

@section('title', 'Reports - Senior Manager')
@section('page-title', 'Reports')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Team Performance</h3>
        <div class="h-64 flex items-center justify-center border-2 border-dashed border-gray-300 rounded-lg">
            <div class="text-center">
                <i class="fas fa-chart-bar text-gray-300 text-4xl mb-2"></i>
                <p class="text-gray-500 text-sm">Performance Chart</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Prospect Conversion</h3>
        <div class="h-64 flex items-center justify-center border-2 border-dashed border-gray-300 rounded-lg">
            <div class="text-center">
                <i class="fas fa-chart-pie text-gray-300 text-4xl mb-2"></i>
                <p class="text-gray-500 text-sm">Conversion Chart</p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900">Reports & Analytics</h2>
        <div class="flex gap-2">
            <select class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                <option>This Week</option>
                <option>This Month</option>
                <option>Last Month</option>
                <option>Custom Range</option>
            </select>
            <button class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                <i class="fas fa-download mr-2"></i>Export
            </button>
        </div>
    </div>

    <div class="text-center py-12">
        <i class="fas fa-chart-line text-gray-300 text-6xl mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Performance Reports</h3>
        <p class="text-gray-500">Detailed performance analytics and reports for your team.</p>
        <p class="text-sm text-gray-400 mt-4">View team performance metrics, conversion rates, and detailed analytics.</p>
    </div>
</div>
@endsection

