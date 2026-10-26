@props(['medicine'])

{{-- A generic pack in the medicine's category colour: a box for tablets and capsules, a bottle
     for liquids. Drawn with SVG attributes, which the CSP allows (inline styles it does not). --}}
@php($colour = $medicine->colour())
@php($edge = $medicine->category === 'cough' ? '#0E3A2F' : $colour)
@if ($medicine->form === 'bottle')
    <svg {{ $attributes->merge(['class' => 'pack pack--bottle']) }} viewBox="0 0 88 120" aria-hidden="true">
        <rect x="30" y="2" width="28" height="16" rx="3" fill="#0E3A2F"/>
        <path d="M33 18h22v10c12 4 21 14 21 28v52a10 10 0 0 1-10 10H22a10 10 0 0 1-10-10V56c0-14 9-24 21-28z" fill="#FFFFFF" stroke="#0E3A2F" stroke-width="2"/>
        <rect x="13" y="60" width="62" height="36" fill="{{ $colour }}" stroke="{{ $edge }}" stroke-width="2"/>
    </svg>
@else
    <svg {{ $attributes->merge(['class' => 'pack pack--box']) }} viewBox="0 0 132 88" aria-hidden="true">
        <rect x="2" y="10" width="128" height="68" rx="8" fill="#FFFFFF" stroke="#0E3A2F" stroke-width="2"/>
        <rect x="3" y="11" width="36" height="66" rx="7" fill="{{ $colour }}"/>
        <rect x="24" y="11" width="15" height="66" fill="{{ $colour }}"/>
        <path d="M39 11v66" stroke="{{ $edge }}" stroke-width="2"/>
        <rect x="52" y="28" width="60" height="8" rx="4" fill="#0E3A2F"/>
        <rect x="52" y="44" width="44" height="6" rx="3" fill="#6E8A81"/>
        <rect x="52" y="58" width="52" height="6" rx="3" fill="#6E8A81"/>
    </svg>
@endif
