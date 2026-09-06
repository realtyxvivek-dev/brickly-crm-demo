@extends('layouts.app')

@section('title', $integration . ' Integration - ' . brand_name())
@section('page-title', $integration . ' Integration')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
        <div class="mb-6">
            <div class="w-24 h-24 mx-auto mb-6 bg-gradient-to-r from-[#063A1C] to-[#205A44] rounded-full flex items-center justify-center">
                <i class="fas fa-plug text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $integration }} Integration</h1>
            <div class="inline-block px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold mb-6">
                Coming Soon
            </div>
        </div>
        
        <p class="text-gray-600 text-lg mb-8">
            The {{ $integration }} integration is currently under development and will be available soon.
        </p>
        
        <div class="bg-gray-50 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">What to Expect</h3>
            <ul class="text-left text-gray-600 space-y-2 max-w-md mx-auto">
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                    <span>Seamless integration with {{ $integration }}</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                    <span>Easy configuration and setup</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                    <span>Automated data synchronization</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                    <span>Real-time updates and notifications</span>
                </li>
            </ul>
        </div>
        
        <a href="{{ route('admin.dashboard') }}" class="inline-block px-6 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>
</div>
@endsection
