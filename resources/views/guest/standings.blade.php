@extends('layouts.guest')

@section('title', 'Klasemen')

@php
    $kategori = $kategori ?? null;
    $kategoriList = $kategoriList ?? collect();
    $standingsQuery = array_filter([
        'id_turnamen' => optional($turnamen)->id,
        'id_kategori' => optional($kategori)->id,
    ]);
@endphp

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $turnamen ?? null,
        'ogUrl' => isset($turnamen) && $turnamen
            ? route('guest.standings', $standingsQuery)
            : route('guest.standings'),
        'ogTitle' => isset($turnamen) && $turnamen
            ? 'Klasemen — ' . $turnamen->nama . ($kategori ? ' · ' . $kategori->nama : '')
            : 'Klasemen — Born Padel',
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold">
                @if (optional($turnamen)->isMahjong())
                    Klasemen Mahjong
                @elseif (optional($turnamen)->isFriendly())
                    Klasemen Group Match
                @else
                    Klasemen Grup
                @endif
            </h1>
            @if ($turnamen)
                <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
                @if ($kategori && optional($kategoriList)->count() > 1)
                    <span class="badge text-bg-primary mt-2">{{ $kategori->nama }}</span>
                @endif
            @endif
        </div>

        @if ($turnamen)
            @include('guest.partials.kategori-selector', [
                'turnamen' => $turnamen,
                'kategori' => $kategori,
                'kategoriList' => $kategoriList,
                'filterRoute' => route('guest.standings'),
                'selectorHint' => 'Klasemen ditampilkan per kategori kompetisi.',
            ])
        @endif

        @if ($turnamen && $turnamen->isMahjong())
            @if (! empty($winners) && ! empty($winners['has_winners']))
                @include('components.partials.bracket-podium-styles')
                @include('components.partials.bracket-podium', [
                    'first' => $winners['first'],
                    'second' => $winners['second'],
                    'third' => $winners['third'],
                ])
            @endif

            <x-mahjong-leaderboard
                :standings="$standings"
                :turnamen="$turnamen"
                :kategori="$kategori"
                :refreshable="true"
            />
        @elseif ($turnamen && $turnamen->isFriendly())
            <x-friendly-leaderboard
                :standings="$standings"
                :turnamen="$turnamen"
                :kategori="$kategori"
                :refreshable="true"
            />
        @else
            <div id="live-leaderboard"
                 data-refresh-url="{{ route('api.guest.standings', array_filter([
                     'id_turnamen' => optional($turnamen)->id,
                     'id_kategori' => optional($kategori)->id,
                 ])) }}"
                 data-profile-base="{{ url('/pemain') }}/"
                 data-show-group-history="1">
                <div id="group-standings-panel">
                    <x-group-leaderboard
                        :standings="$standings"
                        :turnamen="$turnamen"
                        :refreshable="false"
                        :show-group-history="false"
                    />
                </div>
                <div class="d-flex justify-content-end mt-2 mb-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-leaderboard">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
                <div id="post-league-ranking-host">
                    <x-post-league-ranking
                        :ranking="$postLeagueRanking ?? ['sections' => collect()]"
                        :turnamen="$turnamen"
                    />
                </div>
                <p class="text-muted small text-end mt-2 mb-0">
                    <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
                </p>
                <x-group-stage-history-modal :history-url="route('api.guest.standings.group-history')" />
            </div>
        @endif

        <div class="text-center mt-4">
            <a href="{{ route('guest.landing') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if ($turnamen && ! $turnamen->isMahjong() && ! $turnamen->isFriendly())
<script src="{{ asset('public/js/group-stage-history.js') }}"></script>
@endif
<script src="{{ asset('public/js/leaderboard.js') }}"></script>
@endpush
