@php
    $lifeDates ??= null;
@endphp

@if ($lifeDates)
    <p class="saint-life-dates">
        <span class="saint-life-dates-label">Life Dates</span>
        <span class="saint-life-dates-divider" aria-hidden="true"></span>
        <span class="saint-life-dates-value">{{ $lifeDates }}</span>
    </p>
@endif
