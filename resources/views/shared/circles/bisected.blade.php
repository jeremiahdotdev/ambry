@php
    $size ??= 320;
    $x ??= '0';
    $y ??= '0';
    $first ??= '#b7831b';
    $second ??= '#f3ead7';
    $line ??= '#ffffff';
    $opacity ??= 0.5;
    $rotation ??= 0;
    $cssSize ??= null;
    $cut ??= 'straight';
    $id = 'circle-'.\Illuminate\Support\Str::uuid();
    $half = $size / 2;
@endphp

<svg
    class="saint-circle"
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 {{ $size }} {{ $size }}"
    aria-hidden="true"
    style="--circle-x: {{ $x }}; --circle-y: {{ $y }}; --circle-opacity: {{ $opacity }}; --circle-rotation: {{ $rotation }}deg; @if ($cssSize) --circle-size: {{ $cssSize }}; @endif"
>
    <defs>
        <clipPath id="{{ $id }}-left">
            <rect x="0" y="0" width="{{ $half }}" height="{{ $size }}" />
        </clipPath>
        <clipPath id="{{ $id }}-right">
            <rect x="{{ $half }}" y="0" width="{{ $half }}" height="{{ $size }}" />
        </clipPath>
        <clipPath id="{{ $id }}-moon">
            <path d="M {{ $half }} 0 C {{ $size * 0.12 }} {{ $size * 0.28 }}, {{ $size * 0.12 }} {{ $size * 0.72 }}, {{ $half }} {{ $size }} L {{ $size }} {{ $size }} L {{ $size }} 0 Z" />
        </clipPath>
    </defs>

    <g transform="rotate({{ $rotation }} {{ $half }} {{ $half }})">
        @if ($cut === 'curved')
            <circle cx="{{ $half }}" cy="{{ $half }}" r="{{ $half - 1 }}" fill="{{ $first }}" />
            <circle cx="{{ $half }}" cy="{{ $half }}" r="{{ $half - 1 }}" fill="{{ $second }}" clip-path="url(#{{ $id }}-moon)" />
        @else
            <circle cx="{{ $half }}" cy="{{ $half }}" r="{{ $half - 1 }}" fill="{{ $first }}" clip-path="url(#{{ $id }}-left)" />
            <circle cx="{{ $half }}" cy="{{ $half }}" r="{{ $half - 1 }}" fill="{{ $second }}" clip-path="url(#{{ $id }}-right)" />
        @endif
        <circle cx="{{ $half }}" cy="{{ $half }}" r="{{ $half - 1 }}" fill="none" stroke="{{ $line }}" stroke-width="3" />

        @if ($cut === 'curved')
            <path
                d="M {{ $half }} 0 C {{ $size * 0.12 }} {{ $size * 0.28 }}, {{ $size * 0.12 }} {{ $size * 0.72 }}, {{ $half }} {{ $size }}"
                fill="none"
                stroke="{{ $line }}"
                stroke-width="3"
                stroke-linecap="round"
                opacity="0.9"
            />
        @else
            <line x1="{{ $half }}" y1="0" x2="{{ $half }}" y2="{{ $size }}" stroke="{{ $line }}" stroke-width="3" />
            <line x1="0" y1="{{ $half }}" x2="{{ $size }}" y2="{{ $half }}" stroke="{{ $line }}" stroke-width="2" opacity="0.8" />
        @endif
    </g>
</svg>
