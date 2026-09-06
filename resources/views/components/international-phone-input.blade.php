@props([
    'name' => 'phone',
    'countryName' => 'phone_country_iso',
    'id' => 'phone',
    'value' => '',
    'country' => 'IN',
    'required' => false,
    'inputClass' => '',
])

@php
    $phoneService = app(\App\Services\InternationalPhoneService::class);
    $selectedCountry = strtoupper((string) old($countryName, $country ?: 'IN'));
    $phoneValue = old($name, $phoneService->displayLocal($value, $selectedCountry));
@endphp

<div class="international-phone-input" data-phone-input>
    <select name="{{ $countryName }}" id="{{ $id }}_country" data-phone-country aria-label="Country code" class="{{ $inputClass }}">
        @foreach($phoneService->countryOptions() as $iso => $option)
            <option value="{{ $iso }}" {{ $selectedCountry === $iso ? 'selected' : '' }}>{{ $iso }} +{{ $option['code'] }}</option>
        @endforeach
        <option value="">Other (paste + number)</option>
    </select>
    <input type="tel" name="{{ $name }}" id="{{ $id }}" value="{{ $phoneValue }}" {{ $required ? 'required' : '' }}
        placeholder="Mobile number or +country code" autocomplete="tel" data-phone-number class="{{ $inputClass }}">
</div>

@once
    <style>
        .international-phone-input { display:flex; gap:8px; width:100%; }
        .international-phone-input select { flex:0 0 116px; min-width:0; }
        .international-phone-input input { min-width:0; flex:1; }
        @media (max-width: 520px) { .international-phone-input { gap:6px; } .international-phone-input select { flex-basis:96px; } }
    </style>
    <script>
        document.addEventListener('input', function (event) {
            const input = event.target.closest('[data-phone-number]');
            if (!input || !input.value.trim().startsWith('+')) return;

            const digits = input.value.replace(/\D/g, '');
            const select = input.closest('[data-phone-input]').querySelector('[data-phone-country]');
            const codes = @json(collect($phoneService->countryOptions())->mapWithKeys(fn ($country, $iso) => [$iso => $country['code']]));
            const country = Object.entries(codes)
                .sort((a, b) => b[1].length - a[1].length)
                .find(([, code]) => digits.startsWith(code));
            if (country) select.value = country[0];
        });
    </script>
@endonce
