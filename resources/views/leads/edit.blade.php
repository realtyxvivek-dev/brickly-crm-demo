@php
    $user = auth()->user();
    if ($user && !$user->relationLoaded('role')) {
        $user->load('role');
    }
@endphp
@extends('layouts.app')

@section('title', 'Edit Lead Requirements - ' . $lead->name)
@section('page-title', 'Edit Lead Requirements')
@section('page-subtitle', 'Update lead requirement form')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $lead->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    <span class="font-medium">Phone:</span> {{ $lead->phone }}
                    @if($lead->email)
                        | <span class="font-medium">Email:</span> {{ $lead->email }}
                    @endif
                </p>
            </div>
            <a href="{{ route('leads.show', $lead->id) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Lead
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(auth()->user()->isCrm())
        <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg">
            <strong>ℹ️ Note:</strong> As a CRM user, you can view and edit <strong>all fields</strong> from all levels (Sales Executive, and Senior Manager). Fill in the complete lead requirements below.
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

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        @include('leads.partials.centralized-form', ['lead' => $lead])
    </div>
</div>
@endsection
