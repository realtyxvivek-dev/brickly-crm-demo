@extends('layouts.app')

@section('title', 'Attendance Offices')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@section('content')
@php($attendanceRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.attendance' : 'admin.attendance')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Attendance Setup',
        'title' => 'Office locations',
        'subtitle' => 'Configure office geo-fencing, radius, and base attendance locations.',
    ])
    @include('admin.hr._nav')
    @include('admin.attendance._nav')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Total Offices</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $offices->count() }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Active Offices</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $offices->where('is_active', true)->count() }}</div>
        </div>
    </div>

    <div class="hr-setup-help">
        <h2 class="text-lg font-semibold text-brand-primary mb-3">Quick Help</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-[#374151]">
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">What to Fill</div>
                <p>1. Enter the office name.</p>
                <p>2. Add a short office code.</p>
                <p>3. Use Google Maps or current location to capture exact latitude and longitude.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Recommended</div>
                <p>Radius start: <strong>150m - 250m</strong></p>
                <p>If many users punch near the office boundary, increase the radius slightly.</p>
                <p>An accurate map pin is important.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Important</div>
                <p>Geo-fencing works correctly only when latitude and longitude are filled.</p>
                <p>During pilot testing, start with one office first.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">{{ $editingOffice ? 'Edit Office' : 'Add Office' }}</h2>
                <p class="text-sm text-[#6B7280] mt-1">Basic fields are shown first. Open extra details only when needed.</p>
            </div>
            @if($editingOffice)
                <a href="{{ route($attendanceRouteBase . '.offices.index') }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg text-sm text-[#6B7280]">Cancel Edit</a>
            @endif
        </div>
        <form method="POST" action="{{ $editingOffice ? route($attendanceRouteBase . '.offices.update', $editingOffice) : route($attendanceRouteBase . '.offices.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            @if($editingOffice)
                @method('PUT')
            @endif
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Office name</label>
                <input type="text" name="name" value="{{ old('name', $editingOffice?->name) }}" placeholder="Example: Main Office" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Latitude</label>
                <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $editingOffice?->latitude) }}" placeholder="Example: 25.3176452" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" data-office-latitude>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Longitude</label>
                <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $editingOffice?->longitude) }}" placeholder="Example: 82.9739144" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" data-office-longitude>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Radius (meters)</label>
                <input type="number" name="radius_meters" value="{{ old('radius_meters', $editingOffice?->radius_meters ?? 200) }}" min="10" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div class="md:col-span-2 rounded-xl border border-[#E5DED4] bg-[#F7F4EE] p-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-brand-primary">Current Location</div>
                        <div class="text-xs text-[#6B7280]" data-office-location-status>Click the button to fill latitude and longitude automatically.</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="px-4 py-2 bg-[#205A44] text-white rounded-lg text-sm font-medium hover:bg-[#184736]" data-office-location-trigger>
                            Use Current Location
                        </button>
                        <a href="#" target="_blank" rel="noopener" class="px-4 py-2 border border-[#E5DED4] rounded-lg text-sm text-[#205A44] hover:bg-white hidden" data-office-map-link>
                            Open in Google Maps
                        </a>
                    </div>
                </div>
            </div>
            <div class="md:col-span-2">
                <details class="rounded-xl border border-[#E5DED4] p-4">
                    <summary class="cursor-pointer font-semibold text-brand-primary">Advanced Office Details</summary>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Code</label>
                            <input type="text" name="code" value="{{ old('code', $editingOffice?->code) }}" placeholder="Example: MAIN-OFFICE" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-brand-primary"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $editingOffice?->is_active ?? true) ? 'checked' : '' }}> Active office</label>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-[#374151] mb-1">Address</label>
                            <input type="text" name="address" value="{{ old('address', $editingOffice?->address) }}" placeholder="Full office address" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        </div>
                    </div>
                </details>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">{{ $editingOffice ? 'Update Office' : 'Save Office' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <h2 class="text-lg font-semibold text-brand-primary mb-4">Existing Offices</h2>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">Name</th><th>Code</th><th>Radius</th><th>Coords</th><th>Status</th><th class="text-right">Action</th></tr></thead>
            <tbody>
                @forelse($offices as $office)
                    <tr class="border-b last:border-0">
                        <td class="py-3">{{ $office->name }}</td>
                        <td>{{ $office->code ?: '--' }}</td>
                        <td>{{ $office->radius_meters }}m</td>
                        <td>{{ $office->latitude ?: '--' }}, {{ $office->longitude ?: '--' }}</td>
                        <td>
                            <span class="px-2 py-1 rounded-full text-xs {{ $office->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $office->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route($attendanceRouteBase . '.offices.index', ['edit' => $office->id]) }}" class="inline-flex px-3 py-1.5 rounded-lg border border-[#E5DED4] text-[#205A44] hover:bg-[#F7F4EE]">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route($attendanceRouteBase . '.offices.destroy', $office) }}" onsubmit="return confirm('Delete this office?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex px-3 py-1.5 rounded-lg border border-[#FECACA] text-[#B91C1C] hover:bg-[#FEF2F2]">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-[#6B7280]">No offices configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const latitudeInput = document.querySelector('[data-office-latitude]');
    const longitudeInput = document.querySelector('[data-office-longitude]');
    const trigger = document.querySelector('[data-office-location-trigger]');
    const status = document.querySelector('[data-office-location-status]');
    const mapLink = document.querySelector('[data-office-map-link]');

    if (!latitudeInput || !longitudeInput || !trigger || !status || !mapLink) {
        return;
    }

    function updateMapLink() {
        const latitude = latitudeInput.value;
        const longitude = longitudeInput.value;

        if (!latitude || !longitude) {
            mapLink.classList.add('hidden');
            mapLink.removeAttribute('href');
            return;
        }

        mapLink.href = `https://www.google.com/maps?q=${latitude},${longitude}`;
        mapLink.classList.remove('hidden');
    }

    function setStatus(message, isError) {
        status.textContent = message;
        status.classList.toggle('text-[#B91C1C]', !!isError);
        status.classList.toggle('text-[#6B7280]', !isError);
    }

    trigger.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setStatus('Location access is not supported in this browser.', true);
            return;
        }

        trigger.disabled = true;
        trigger.classList.add('opacity-70', 'cursor-not-allowed');
        setStatus('Fetching your current location...', false);

        navigator.geolocation.getCurrentPosition(
            function (position) {
                latitudeInput.value = position.coords.latitude.toFixed(7);
                longitudeInput.value = position.coords.longitude.toFixed(7);
                updateMapLink();
                setStatus('Latitude and longitude filled from your current location.', false);
                trigger.disabled = false;
                trigger.classList.remove('opacity-70', 'cursor-not-allowed');
            },
            function (error) {
                let message = 'Unable to fetch current location.';

                if (error.code === error.PERMISSION_DENIED) {
                    message = 'Location permission denied. Allow access and try again.';
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    message = 'Current location is unavailable on this device.';
                } else if (error.code === error.TIMEOUT) {
                    message = 'Location request timed out. Try again.';
                }

                setStatus(message, true);
                trigger.disabled = false;
                trigger.classList.remove('opacity-70', 'cursor-not-allowed');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            }
        );
    });

    latitudeInput.addEventListener('input', updateMapLink);
    longitudeInput.addEventListener('input', updateMapLink);
    updateMapLink();
});
</script>
@endpush
