@php
    $selectedVerifierRoleIds = $mapping?->verifier_role_ids ?? [];
@endphp

<div class="grid gap-3 md:grid-cols-2">
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Workflow
        <select name="workflow_type" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            @foreach($workflows as $workflowType => $workflowLabel)
                <option value="{{ $workflowType }}" @selected(($mapping?->workflow_type ?? old('workflow_type')) === $workflowType)>{{ $workflowLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Priority
        <input type="number" name="priority" value="{{ $mapping?->priority ?? old('priority', 100) }}" min="1" max="9999" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
    </label>
</div>

<div class="grid gap-3 md:grid-cols-4">
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Source type
        <select name="source_type" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            @foreach($sourceTypes as $sourceType => $sourceLabel)
                <option value="{{ $sourceType }}" @selected(($mapping?->source_type ?? old('source_type')) === $sourceType)>{{ $sourceLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Source user
        <select name="source_user_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            <option value="">None</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) ($mapping?->source_user_id ?? old('source_user_id')) === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Source role
        <select name="source_role_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            <option value="">None</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected((int) ($mapping?->source_role_id ?? old('source_role_id')) === (int) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Source team owner
        <select name="source_team_user_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            <option value="">None</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) ($mapping?->source_team_user_id ?? old('source_team_user_id')) === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-3 md:grid-cols-3">
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Verifier type
        <select name="verifier_type" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            @foreach($verifierTypes as $verifierType => $verifierLabel)
                <option value="{{ $verifierType }}" @selected(($mapping?->verifier_type ?? old('verifier_type')) === $verifierType)>{{ $verifierLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Verifier user
        <select name="verifier_user_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            <option value="">None</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) ($mapping?->verifier_user_id ?? old('verifier_user_id')) === (int) $user->id)>{{ $user->name }} — {{ $user->role?->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Verifier role(s)
        <select name="verifier_role_ids[]" multiple class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected(in_array($role->id, $selectedVerifierRoleIds))>{{ $role->name }}</option>
            @endforeach
        </select>
    </label>
</div>

<label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-700" @checked($mapping?->is_active ?? true)>
    Active mapping
</label>
