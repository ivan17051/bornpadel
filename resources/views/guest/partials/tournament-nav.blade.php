@if (! empty($turnamen))
@php
    $kategori = $kategori ?? null;
    $kategoriList = $kategoriList
        ?? ($turnamen->relationLoaded('kategori')
            ? $turnamen->kategori->sortBy([['urutan', 'asc'], ['id', 'asc']])->values()
            : $turnamen->kategori()->orderBy('urutan')->orderBy('id')->get());

    $query = array_filter([
        'id_turnamen' => $turnamen->id,
        'id_kategori' => optional($kategori)->id,
    ]);

    $routeName = Route::currentRouteName();
    $active = $activeTab ?? (
        in_array($routeName, ['guest.register', 'guest.register.form', 'guest.register.lookup'], true)
            ? 'register'
            : (in_array($routeName, ['guest.participants'], true)
                ? 'participants'
                : (in_array($routeName, ['guest.standings'], true)
                    ? 'standings'
                    : (in_array($routeName, ['guest.bracket'], true)
                        ? 'bracket'
                        : null)))
    );

    if ($kategori) {
        $registrationOpen = $kategori->isRegistrationOpen();
    } elseif ($kategoriList instanceof \Illuminate\Support\Collection && $kategoriList->isNotEmpty()) {
        $registrationOpen = $kategoriList->contains(function ($item) {
            return $item->isRegistrationOpen();
        });
    } else {
        $registrationOpen = $turnamen->isRegistrationOpen();
    }

    $showBracket = $turnamen->usesKnockoutBracket();

    $tabs = [];

    if ($registrationOpen) {
        $tabs[] = [
            'key' => 'register',
            'label' => 'Daftar',
            'icon' => 'bi-pencil-square',
            'url' => route('guest.register', $query),
        ];
    }

    $tabs[] = [
        'key' => 'participants',
        'label' => 'Peserta',
        'icon' => 'bi-people',
        'url' => route('guest.participants', $query),
    ];

    $tabs[] = [
        'key' => 'standings',
        'label' => 'Klasemen',
        'icon' => 'bi-bar-chart-steps',
        'url' => route('guest.standings', $query),
    ];

    if ($showBracket) {
        $tabs[] = [
            'key' => 'bracket',
            'label' => 'Bracket',
            'icon' => 'bi-diagram-2',
            'url' => route('guest.bracket', $query),
        ];
    }
@endphp

@if (count($tabs) > 0)
@once
@push('styles')
<style>
    .guest-tournament-nav {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.4rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding: 0.35rem;
        margin-bottom: 1.25rem;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 0.85rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        scrollbar-width: thin;
    }

    .guest-tournament-nav::-webkit-scrollbar {
        height: 4px;
    }

    .guest-tournament-nav::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.12);
        border-radius: 4px;
    }

    .guest-tournament-nav__link {
        flex: 1 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.55rem 0.9rem;
        border-radius: 0.6rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: #5c5c5c;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid transparent;
        transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }

    .guest-tournament-nav__link:hover {
        color: var(--bp-primary-dark);
        background: rgba(205, 168, 88, 0.12);
    }

    .guest-tournament-nav__link.is-active {
        color: #1a1a1a;
        background: rgba(205, 168, 88, 0.28);
        border-color: rgba(205, 168, 88, 0.45);
    }

    .guest-tournament-nav__link i {
        font-size: 1rem;
    }

    @media (max-width: 576px) {
        .guest-tournament-nav__label {
            font-size: 0.82rem;
        }
    }
</style>
@endpush
@endonce

<nav class="guest-tournament-nav" aria-label="Menu turnamen">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}"
           class="guest-tournament-nav__link {{ $active === $tab['key'] ? 'is-active' : '' }}"
           @if ($active === $tab['key']) aria-current="page" @endif>
            <i class="bi {{ $tab['icon'] }}" aria-hidden="true"></i>
            <span class="guest-tournament-nav__label">{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
@endif
@endif
