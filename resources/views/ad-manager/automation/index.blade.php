@extends('layouts.app')

@section('title', 'Source Automation - ' . brand_name())
@section('page-title', 'Source Automation')

@section('content')
<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Meta Source Automation</h1>
            <p class="mt-1 text-sm text-slate-500">Meta, Meta Awareness, Facebook Lead Ads, WhatsApp, and Instagram assignment rules.</p>
        </div>
        <a href="{{ route('ad-manager.automation.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            <i class="fas fa-plus"></i>
            New Rule
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Rule</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Source</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Users</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rules as $rule)
                    @php
                        $attachedForms = $rule->relationLoaded('fbForms') ? $rule->fbForms : collect();
                        if ($attachedForms->isEmpty() && $rule->fbForm) {
                            $attachedForms = collect([$rule->fbForm]);
                        }
                        $formNames = $attachedForms
                            ->map(fn ($form) => $form->form_name ?: $form->form_id)
                            ->filter()
                            ->values();
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $rule->name }}</div>
                            <div class="text-xs text-slate-500">
                                @if($formNames->isNotEmpty())
                                    {{ $formNames->take(3)->implode(', ') }}
                                    @if($formNames->count() > 3)
                                        +{{ $formNames->count() - 3 }} more
                                    @endif
                                    <span class="ml-1 text-slate-400">({{ $formNames->count() }} {{ \Illuminate\Support\Str::plural('form', $formNames->count()) }} selected)</span>
                                @else
                                    {{ $rule->source_label ?: 'Meta source' }}
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ \App\Models\SourceAutomationRule::getSourceLabel($rule->effectiveSourceType()) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ \App\Models\SourceAutomationRule::getMethodLabel($rule->effectiveDistributionMethod()) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            @if($rule->assignment_method === 'single_user')
                                {{ $rule->singleUser?->name ?: 'Not set' }}
                            @else
                                {{ $rule->users->count() }} selected
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $rule->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $rule->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('ad-manager.automation.edit', $rule) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                <button type="button" data-toggle-url="{{ route('ad-manager.automation.toggle', $rule) }}" class="js-toggle rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    {{ $rule->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center">
                            <div class="text-sm font-semibold text-slate-900">No Meta automation rules yet.</div>
                            <a href="{{ route('ad-manager.automation.create') }}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                <i class="fas fa-plus"></i>
                                Create Rule
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.js-toggle').forEach((button) => {
    button.addEventListener('click', async () => {
        const response = await fetch(button.dataset.toggleUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });
        if (response.ok) {
            window.location.reload();
        }
    });
});
</script>
@endpush
@endsection
