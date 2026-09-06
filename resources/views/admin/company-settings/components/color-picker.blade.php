@php
    $name = $name ?? 'color';
    $label = $label ?? 'Color';
    $value = $value ?? '#000000';
    $required = $required ?? false;
    $helpText = $helpText ?? null;
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-brand-primary mb-2">
        {{ $label }}
        @if($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="flex gap-2">
        <div class="relative flex-1">
            <input type="color" 
                   name="{{ $name }}" 
                   id="{{ $name }}"
                   value="{{ $value }}"
                   @if($required) required @endif
                   onchange="updateColorPreview('{{ $name }}', this.value)"
                   class="w-full h-10 rounded-lg border border-[#E5DED4] cursor-pointer">
        </div>
        <input type="text" 
               name="{{ $name }}_hex" 
               id="{{ $name }}_hex"
               value="{{ $value }}"
               pattern="^#[0-9A-Fa-f]{6}$"
               onchange="updateColorFromHex('{{ $name }}', this.value)"
               class="w-32 px-3 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand font-mono text-sm text-brand-primary">
    </div>
    @if($helpText)
        <p class="text-xs text-[#B3B5B4] mt-1">{{ $helpText }}</p>
    @endif
</div>
