<div class="saint-copy-panel">
    @include('saints.title-block', [
        'name' => $saint->displayName(),
        'kicker' => $saint->displayCanonicalStatus(),
        'subtitle' => $subtitle ?? null,
        'lifeDates' => $saint->displayProfileLifeDates() ?? $saint->displayLifeDates(),
    ])

    @if ($saint->patronages->isNotEmpty())
        <section class="saint-patronages" aria-labelledby="saint-patronages-title">
            <h2 id="saint-patronages-title">Patronages</h2>
            <ul>
                @foreach ($saint->patronages as $patronage)
                    <li>
                        <a href="{{ route('search.results', ['q' => $patronage->name]) }}">{{ $patronage->name }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @php($profileRoles = $saint->displayProfileRoles())

    @if ($profileRoles !== [])
        <section class="saint-patronages saint-profile-roles" aria-labelledby="saint-profile-roles-title">
            <h2 id="saint-profile-roles-title">Roles</h2>
            <ul>
                @foreach ($profileRoles as $role)
                    <li>
                        <span>{{ $role }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <span class="saint-divider"></span>

    @if (filled($saint->profile_summary))
        <div class="saint-intro saint-profile-summary">
            @foreach ($saint->displayProfileSummaryParagraphs() as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @elseif (! empty($saint->biography_sections))
        @include('saints.biography-sections', ['saint' => $saint])
    @elseif ($saint->displayBiography())
        <div class="saint-intro">
            @foreach ($saint->displayBiographyParagraphs() as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    @include('saints.profile-enrichment', ['saint' => $saint])
</div>
