@extends('layouts.app')

@section('title', $journey->exists ? 'Edit Journey' : 'Create Journey')
@section('page-title', $journey->exists ? 'Edit Journey' : 'Create Journey')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
    <form method="POST" action="{{ $journey->exists ? route($routeBase . '.journeys.update', $journey) : route($routeBase . '.journeys.store') }}" class="space-y-6">
        @csrf
        @if($journey->exists)
            @method('PUT')
        @endif
        <div><label class="block text-sm font-medium text-slate-700 mb-2">Journey name</label><input type="text" name="name" value="{{ old('name', $journey->name) }}" class="w-full rounded-xl border-slate-300" required></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-2">Journey type</label><input type="text" name="category" value="{{ old('category', $journey->category) }}" class="w-full rounded-xl border-slate-300" placeholder="welcome, visit, meeting"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-2">Default template</label><select name="template_id" class="w-full rounded-xl border-slate-300"><option value="">Select template later</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected(old('template_id', $journey->template_id) == $template->id)>{{ $template->name }}</option>@endforeach</select><p class="text-xs text-slate-500 mt-2">Individual rules can still use their own template.</p></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-2">Description</label><textarea name="description" rows="4" class="w-full rounded-xl border-slate-300">{{ old('description', $journey->description) }}</textarea></div>
        <div class="grid md:grid-cols-3 gap-4">
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Status</label><select name="status" class="w-full rounded-xl border-slate-300">@foreach(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused'] as $statusValue => $statusLabel)<option value="{{ $statusValue }}" @selected(old('status', $journey->status ?: 'draft') === $statusValue)>{{ $statusLabel }}</option>@endforeach</select></div>
            <label class="flex items-center gap-2 text-sm text-slate-700 mt-8"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $journey->is_active))> Active / paused</label>
            <label class="flex items-center gap-2 text-sm text-slate-700 mt-8"><input type="checkbox" name="test_mode" value="1" @checked(old('test_mode', $journey->test_mode))> Test mode</label>
        </div>
        <div class="flex gap-3"><button type="submit" class="px-5 py-3 rounded-xl bg-teal-700 text-white font-semibold">Save journey</button><a href="{{ route($routeBase . '.index', ['tab' => 'journeys']) }}" class="px-5 py-3 rounded-xl border border-slate-300 text-slate-700 font-semibold">Back</a></div>
    </form>
</div>
@endsection
