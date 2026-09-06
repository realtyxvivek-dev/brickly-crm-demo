@php
    $sourceClass = match($rule->source) {
        'facebook_lead_ads' => 'facebook',
        'pabbly'            => 'pabbly',
        'mcube'             => 'mcube',
        '99acres'           => 'nna',
        'instagram'         => 'instagram',
        'all'               => 'all',
        default             => 'other',
    };
    $sourceIcon = match($rule->source) {
        'facebook_lead_ads' => 'fab fa-facebook',
        'pabbly'            => 'fas fa-bolt',
        'mcube'             => 'fas fa-phone',
        '99acres'           => 'fas fa-building',
        'instagram'         => 'fab fa-instagram',
        'all'               => 'fas fa-globe',
        default             => 'fas fa-table',
    };
    $attachedForms = $rule->relationLoaded('fbForms') ? $rule->fbForms : collect();
    if ($attachedForms->isEmpty() && $rule->fbForm) {
        $attachedForms = collect([$rule->fbForm]);
    }
    $formNames = $attachedForms
        ->map(fn ($form) => $form->form_name ?: $form->form_id)
        ->filter()
        ->values();
@endphp

<div class="rule-card {{ !$rule->is_active ? 'inactive' : '' }}">
    <div class="rule-card-header">
        <div class="rule-avatar {{ $sourceClass }}">
            <i class="{{ $sourceIcon }}"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-base font-semibold text-gray-900 truncate">{{ $rule->name }}</h3>
            <p class="text-xs text-gray-500 mt-0.5" data-rule-status>
                @if($rule->is_active)
                    <span class="inline-flex items-center gap-1 text-green-600 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Active
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-gray-400 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span> Paused
                    </span>
                @endif
            </p>
        </div>
    </div>

    <div class="rule-card-body">
        <div class="rule-meta-item">
            <i class="fas fa-layer-group"></i>
            {{ \App\Models\SourceAutomationRule::getSourceLabel($rule->source) }}
        </div>
        @if($formNames->isNotEmpty())
            <div class="rule-meta-item">
                <i class="fas fa-file-alt"></i>
                {{ $formNames->take(2)->implode(', ') }}
                @if($formNames->count() > 2)
                    +{{ $formNames->count() - 2 }} more
                @endif
                ({{ $formNames->count() }} {{ \Illuminate\Support\Str::plural('form', $formNames->count()) }})
            </div>
        @endif
        <div class="rule-meta-item">
            <i class="fas fa-random"></i>
            {{ \App\Models\SourceAutomationRule::getMethodLabel($rule->assignment_method) }}
        </div>
        <div class="rule-meta-item {{ $rule->auto_create_task ? 'task-on' : '' }}">
            <i class="fas {{ $rule->auto_create_task ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
            Task {{ $rule->auto_create_task ? 'ON' : 'OFF' }}
        </div>
        @if($rule->daily_limit)
            <div class="rule-meta-item">
                <i class="fas fa-tachometer-alt"></i>
                Limit: {{ $rule->daily_limit }}/day
            </div>
        @endif

        @if($rule->assignment_method === 'single_user' && $rule->singleUser)
            <div class="user-chips">
                <span class="user-chip">
                    <i class="fas fa-user" style="font-size:0.6rem;"></i>
                    {{ $rule->singleUser->name }}
                </span>
            </div>
        @elseif($rule->users->isNotEmpty())
            <div class="user-chips">
                @foreach($rule->users->take(4) as $ru)
                    <span class="user-chip">
                        {{ $ru->user->name ?? 'Unknown' }}
                        @if($ru->percentage)<span style="opacity:0.55"> {{ $ru->percentage }}%</span>@endif
                    </span>
                @endforeach
                @if($rule->users->count() > 4)
                    <span class="user-chip more">+{{ $rule->users->count() - 4 }}</span>
                @endif
            </div>
        @endif
    </div>

    <div class="rule-card-footer">
        <button class="btn-action btn-toggle-rule {{ $rule->is_active ? 'btn-toggle-pause' : 'btn-toggle-play' }}"
                data-id="{{ $rule->id }}" data-active="{{ $rule->is_active ? 1 : 0 }}"
                data-url="{{ route('admin.automation.toggle', $rule) }}"
                title="{{ $rule->is_active ? 'Pause' : 'Resume' }}">
            <i class="fas {{ $rule->is_active ? 'fa-pause' : 'fa-play' }}"></i>
            {{ $rule->is_active ? 'Pause' : 'Resume' }}
        </button>

        <a href="{{ route('admin.automation.edit', $rule) }}" class="btn-action btn-edit">
            <i class="fas fa-pen"></i> Edit
        </a>

        <button class="btn-action btn-delete btn-delete-rule full"
                data-id="{{ $rule->id }}" data-name="{{ $rule->name }}">
            <i class="fas fa-trash-alt"></i> Delete
        </button>
        <a href="{{ route('admin.automation.history', $rule) }}"
           class="btn-action full"
           style="background:linear-gradient(to right,#0369a1,#0284c7);color:#fff;text-decoration:none;text-align:center;">
            <i class="fas fa-history"></i> History
        </a>
    </div>
</div>
