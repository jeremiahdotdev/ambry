@php
    $size ??= 320;
    $x ??= '0';
    $y ??= '0';
    $first ??= '#b7831b';
    $second ??= '#f3ead7';
    $line ??= '#d9a65e';
    $opacity ??= 0.5;
    $rotation ??= 0;
    $cssSize ??= null;
@endphp

<div
    class="saint-circle"
    aria-hidden="true"
    style="--circle-x: {{ $x }}; --circle-y: {{ $y }}; --circle-opacity: {{ $opacity }}; --circle-rotation: {{ $rotation }}deg; --circle-color: {{ $first }}; --circle-route: {{ $second }}; --circle-line: {{ $line }}; @if ($cssSize) --circle-size: {{ $cssSize }}; @else --circle-size: {{ $size }}px; @endif"
></div>
