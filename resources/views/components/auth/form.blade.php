@props([
    'title',
    'action',
    'submit',
    'method' => 'POST',
])

<section class="developer-auth-panel" aria-label="{{ $title }}">
    <p class="eyebrow">Developers</p>
    <h1>{{ $title }}</h1>

    @if ($errors->any())
        <div class="message" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="{{ $method }}" action="{{ $action }}" class="developer-auth-form">
        @csrf

        {{ $slot }}

        <x-form.button>{{ $submit }}</x-form.button>
    </form>

    @isset($footer)
        <div class="developer-auth-footer">
            {{ $footer }}
        </div>
    @endisset
</section>
