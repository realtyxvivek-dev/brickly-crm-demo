@props([
    'type' => 'button',
    'variant' => 'primary', // primary, secondary, danger
    'size' => 'md', // sm, md, lg
    'class' => '',
])

@php
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
    ];
    
    $variantClasses = [
        'primary' => 'btn-brand-gradient text-white',
        'secondary' => 'btn-brand-secondary text-white',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white',
    ];
    
    $baseClasses = 'rounded-lg transition-colors duration-200 font-medium';
    $classes = $sizeClasses[$size] . ' ' . $variantClasses[$variant] . ' ' . $baseClasses . ' ' . $class;
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
