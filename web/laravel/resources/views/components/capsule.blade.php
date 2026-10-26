@props(['progress' => null])

{{-- The capsule illustration from the landing page design. public/js/site.js places every part for a
     progress from 0 (closed capsule) to 1 (granules sorted into the week organiser): a fixed one from
     data-progress, or, without it, one that follows the page scroll. --}}
<svg {{ $attributes->merge(['class' => 'capsule']) }} viewBox="0 0 480 480" role="img" aria-label="Capsule"
     data-capsule @if (! is_null($progress)) data-progress="{{ $progress }}" @endif>
    <ellipse data-part="shadow" cx="240" cy="403" rx="130" ry="11" fill="#E2E7E1"/>

    <g data-part="organiser">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $day)
            <rect x="{{ 31 + $i * 61 }}" y="317" width="52" height="82" rx="13" fill="#FFFFFF" stroke="#0E3A2F" stroke-width="2"/>
            <text class="capsule-day" x="{{ 57 + $i * 61 }}" y="418" text-anchor="middle">{{ $day }}</text>
        @endforeach
    </g>

    {{-- 21 granules, three per day: blue, yellow, white with a dark ring --}}
    @for ($i = 0; $i < 21; $i++)
        @if ($i % 3 === 2)
            <circle data-part="granule" r="7" fill="#FFFFFF" stroke="#0E3A2F" stroke-width="2"/>
        @else
            <circle data-part="granule" r="8" fill="{{ $i % 3 === 0 ? '#2A4BD7' : '#F2C94C' }}"/>
        @endif
    @endfor

    <g data-part="pill">
        <g data-part="body">
            <path d="M -111 -59 H 40 V 59 H -111 A 59 59 0 0 1 -111 -59 Z" fill="#F2C94C"/>
            <rect x="-136" y="-39" width="120" height="14" rx="7" fill="#FFFFFF" fill-opacity="0.45"/>
            <ellipse data-part="rim" cx="40" cy="0" rx="14" ry="59" fill="#CFA22A"/>
        </g>
        <g data-part="cap">
            <path d="M 0 -64 H 106 A 64 64 0 0 1 106 64 H 0 Z" fill="#2A4BD7"/>
            <ellipse data-part="rim" cx="0" cy="0" rx="14" ry="64" fill="#1B339E"/>
            <rect x="22" y="-42" width="96" height="14" rx="7" fill="#FFFFFF" fill-opacity="0.28"/>
        </g>
    </g>
</svg>
