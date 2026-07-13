@props(['bracket', 'turnamen' => null, 'refreshable' => false, 'editable' => false])

@php
    $leafCount = 1;
    $hasThirdPlace = false;
    $roundCount = 0;
    if (! empty($bracket)) {
        $firstRound = $bracket[0]['matches'] ?? [];
        $leafCount = max(1, collect($firstRound)->filter(fn ($m) => empty($m['is_third_place']))->count());
        $hasThirdPlace = collect($bracket)->flatMap(fn ($r) => $r['matches'])->contains(fn ($m) => ! empty($m['is_third_place']));
        $roundCount = count($bracket);
    }
@endphp

<div class="tournament-bracket-wrapper"
     data-profile-base="{{ url('/pemain') }}/"
     @if($refreshable || $editable)
         id="live-bracket"
         data-refresh-url="{{ route('api.guest.bracket', array_filter(['id_turnamen' => optional($turnamen)->id])) }}"
         data-leaf-count="{{ $leafCount }}"
         data-has-third="{{ $hasThirdPlace ? '1' : '0' }}"
         data-photo-placeholder="{{ app(\App\Services\PemainPhotoService::class)->placeholderUrl() }}"
     @endif
     @if($editable)
         data-editable="1"
         data-swap-url="{{ route('admin.bracket.swap') }}"
         data-turnamen="{{ optional($turnamen)->id }}"
     @endif>
    @if ($turnamen)
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">
                <i class="bi bi-diagram-2 me-2"></i>Bracket Knockout
                <small class="text-muted fw-normal">— {{ $turnamen->nama }}</small>
            </h5>
            @if ($refreshable)
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-bracket">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            @endif
        </div>
    @endif

    @if (empty($bracket))
        <div class="alert alert-light border text-center mb-0">
            <i class="bi bi-diagram-2 text-muted d-block mb-2 fs-4"></i>
            Bracket knockout belum tersedia. Selesaikan fase grup terlebih dahulu.
        </div>
    @else
        <div id="bracket-dynamic">
        @php
            $finalRound = collect($bracket)->firstWhere('nama_ronde', 'Final');
            $finalMatch = $finalRound
                ? collect($finalRound['matches'])->first(fn ($m) => empty($m['is_third_place']))
                : null;
            $thirdMatch = collect($bracket)->flatMap(fn ($r) => $r['matches'])->firstWhere('is_third_place', true);
            $podiumFirst = ($finalMatch && ! empty($finalMatch['pemenang']))
                ? ['label' => $finalMatch['pemenang'], 'players' => $finalMatch['pemenang_players'] ?? []]
                : null;
            $podiumSecond = ($finalMatch && ! empty($finalMatch['runner_up']))
                ? ['label' => $finalMatch['runner_up'], 'players' => $finalMatch['runner_up_players'] ?? []]
                : null;
            $podiumThird = ($thirdMatch && ! empty($thirdMatch['pemenang']))
                ? ['label' => $thirdMatch['pemenang'], 'players' => $thirdMatch['pemenang_players'] ?? []]
                : null;
        @endphp
        @include('components.partials.bracket-podium', [
            'first' => $podiumFirst,
            'second' => $podiumSecond,
            'third' => $podiumThird,
        ])
        <div class="bracket-tree overflow-auto pb-3">
            <div class="bracket-board"
                 style="--leaf-count: {{ $leafCount }}; --round-count: {{ $roundCount }};">
                <svg class="bracket-svg" aria-hidden="true"></svg>
                <div class="bracket-cols">
                    @foreach ($bracket as $roundIndex => $round)
                        @php
                            $progression = collect($round['matches'])->filter(fn ($m) => empty($m['is_third_place']))->values();
                            $extras = collect($round['matches'])->filter(fn ($m) => ! empty($m['is_third_place']))->values();
                            $matchCount = max(1, $progression->count());
                            $stride = (int) ($leafCount / $matchCount);
                            $isFinalCol = $round['nama_ronde'] === 'Final';
                        @endphp
                        <div class="bracket-col {{ $isFinalCol ? 'bracket-col--final' : '' }}">
                            <div class="bracket-col-title text-center text-uppercase small fw-bold text-muted mb-3">
                                {{ $round['nama_ronde'] }}
                            </div>
                            <div class="bracket-flex bracket-flex--tree">
                                @foreach ($progression as $matchIndex => $match)
                                    @php
                                        $matchEditable = $editable
                                            && $roundIndex === 0
                                            && empty($match['skor'])
                                            && ($match['status'] !== 'completed' || ! empty($match['is_bye']));
                                        $slot1Editable = $matchEditable && $match['pemain1_id'];
                                        $slot2Editable = $matchEditable && $match['pemain2_id'];
                                        $showFirstLabel = $isFinalCol && $extras->isNotEmpty();
                                    @endphp
                                    <div class="bracket-slot" style="flex: {{ $stride }} 1 0;">
                                        <div class="bracket-node"
                                             data-match-id="{{ $match['id'] }}"
                                             data-next-win="{{ $match['id_next_pertandingan'] ?? '' }}"
                                             data-next-lose="{{ $match['id_next_pertandingan_kalah'] ?? '' }}">
                                            @if ($showFirstLabel)
                                                <div class="bracket-subround-title bracket-subround-title-first text-center text-uppercase small fw-bold mb-2">
                                                    <i class="bi bi-trophy me-1"></i>Perebutan Juara 1
                                                </div>
                                            @endif
                                            @include('components.partials.bracket-match-card', [
                                                'match' => $match,
                                                'slot1Editable' => $slot1Editable,
                                                'slot2Editable' => $slot2Editable,
                                                'isThirdPlace' => false,
                                            ])
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if ($extras->isNotEmpty())
                                <div class="bracket-flex bracket-flex--extra">
                                    @foreach ($extras as $match)
                                        <div class="bracket-slot bracket-slot--extra">
                                            <div class="bracket-node bracket-node--third"
                                                 data-match-id="{{ $match['id'] }}"
                                                 data-next-win=""
                                                 data-next-lose="">
                                                <div class="bracket-subround-title text-center text-uppercase small fw-bold mb-2">
                                                    <i class="bi bi-award me-1"></i>Perebutan Juara 3
                                                </div>
                                                @include('components.partials.bracket-match-card', [
                                                    'match' => $match,
                                                    'slot1Editable' => false,
                                                    'slot2Editable' => false,
                                                    'isThirdPlace' => true,
                                                ])
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($refreshable)
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>
        @endif
        </div>
    @endif
</div>

@once
@push('styles')
<style>
    .tournament-bracket-wrapper { width: 100%; }
    .bracket-tree { min-height: 200px; padding-top: 0.25rem; width: 100%; }
    .bracket-board {
        position: relative;
        width: 100%;
        min-width: max(100%, calc(var(--round-count) * 300px + (var(--round-count) - 1) * 2.5rem));
        padding: 1rem 0.5rem;
        box-sizing: border-box;
    }
    .bracket-svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0;
        overflow: visible;
    }
    .bracket-svg path {
        fill: none;
        stroke-width: 2;
        stroke-linecap: square;
        stroke-linejoin: miter;
    }
    .bracket-svg path.bracket-line--win { stroke: #adb5bd; }
    .bracket-svg path.bracket-line--lose { stroke: #cd7f32; stroke-dasharray: 6 4; }
    .bracket-cols {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: stretch;
        gap: 2.5rem;
        width: 100%;
    }
    .bracket-col {
        flex: 1 1 0;
        min-width: 300px;
        width: 0;
        display: flex;
        flex-direction: column;
    }
    .bracket-col-title { letter-spacing: 0.06em; flex-shrink: 0; }
    .bracket-flex {
        display: flex;
        flex-direction: column;
        width: 100%;
    }
    .bracket-flex--tree {
        flex: 1 1 auto;
        min-height: calc(var(--leaf-count) * 5.5rem);
    }
    .bracket-flex--extra {
        flex: 0 0 auto;
        margin-top: 1.25rem;
        padding-top: 0.75rem;
        border-top: 1px dashed #dee2e6;
    }
    .bracket-slot {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 0;
        padding: 0.25rem 0;
    }
    .bracket-slot--extra {
        flex: 0 0 auto;
    }
    .bracket-node {
        width: 100%;
        min-width: 0;
    }
    .bracket-subround-title { letter-spacing: 0.06em; color: #cd7f32; }
    .bracket-subround-title-first { color: #cda858; }
    .bracket-subround-title .bi-award { color: #cd7f32; }
    .bracket-subround-title-first .bi-trophy { color: #cda858; }
    .bracket-match {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        overflow: visible;
        box-shadow: 0 2px 8px rgba(0,0,0,.04);
        position: relative;
        width: 100%;
        min-width: 300px;
        box-sizing: border-box;
    }
    .bracket-match.is-third-place { border-color: #cd7f32; }
    .bracket-match.is-third-place.has-winner { box-shadow: 0 4px 12px rgba(205, 127, 50, .18); }
    .bracket-match.is-completed { border-color: #cda858; }
    .bracket-match.has-winner { box-shadow: 0 4px 12px rgba(205, 168, 88, .18); }
    .bracket-player {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 0.5rem 0.7rem;
        font-size: 0.84rem;
        border-bottom: 1px solid #f0f0f0;
        gap: 0.5rem;
        background: #fff;
    }
    .bracket-player:first-of-type { border-radius: 0.5rem 0.5rem 0 0; }
    .bracket-player:last-of-type { border-bottom: none; border-radius: 0 0 0.5rem 0.5rem; }
    .bracket-player.is-winner { background: #f5ecd4; font-weight: 600; }
    .bracket-player.is-tbd { color: #adb5bd; font-style: italic; }
    .bracket-player.is-editable { cursor: pointer; transition: background-color .15s ease; }
    .bracket-player.is-editable:hover,
    .bracket-player.is-editable:focus-visible { background: #fbf4e2; outline: none; }
    .bracket-player.is-editable::after {
        content: "\21C4";
        font-size: 0.75rem;
        color: #cda858;
        margin-left: 0.25rem;
        opacity: 0;
        transition: opacity .15s ease;
        flex-shrink: 0;
    }
    .bracket-player.is-editable:hover::after,
    .bracket-player.is-editable:focus-visible::after { opacity: 1; }
    .bracket-player-name { line-height: 1.35; word-break: break-word; }
    .bracket-player-name .pemain-profile-link,
    .bracket-podium-names .pemain-profile-link {
        color: inherit;
        font-weight: inherit;
    }
    .bracket-player.is-winner .pemain-profile-link {
        font-weight: 600;
    }
    .bracket-score-badge { font-size: 0.72rem; color: #6c757d; font-weight: 600; flex-shrink: 0; }
    .bracket-match-status { position: absolute; top: -0.6rem; right: 0.45rem; z-index: 2; line-height: 1; }
    .bracket-match-status .badge { white-space: nowrap; font-size: 0.62rem; }
    .bracket-podium {
        width: 100%;
    }
    .bracket-podium-grid {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: stretch;
        gap: 0.85rem;
    }
    .bracket-podium-card {
        flex: 1 1 180px;
        max-width: 280px;
        text-align: center;
        padding: 1rem 0.85rem;
        border-radius: 0.75rem;
        border: 1px solid #dee2e6;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,.05);
    }
    .bracket-podium-card--first {
        flex: 1.15 1 200px;
        max-width: 300px;
        background: linear-gradient(160deg, #fff9e6 0%, #fff3cd 100%);
        border-color: #ffc107;
        order: 2;
    }
    .bracket-podium-card--second {
        background: linear-gradient(160deg, #f4f6f8 0%, #e9ecef 100%);
        border-color: #adb5bd;
        order: 1;
    }
    .bracket-podium-card--third {
        background: linear-gradient(160deg, #fbf3ec 0%, #f3e2d2 100%);
        border-color: #cd7f32;
        color: #8a5a2b;
        order: 3;
    }
    .bracket-podium-rank {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 0.7rem;
        color: #6c757d;
    }
    .bracket-podium-card--first .bracket-podium-rank { color: #a07800; }
    .bracket-podium-card--first .bracket-podium-rank .bi { color: #ffc107; font-size: 1.1rem; }
    .bracket-podium-card--second .bracket-podium-rank .bi { color: #868e96; font-size: 1rem; }
    .bracket-podium-card--third .bracket-podium-rank { color: #8a5a2b; }
    .bracket-podium-card--third .bracket-podium-rank .bi { color: #cd7f32; font-size: 1rem; }
    .bracket-podium-photos {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 0.65rem;
        flex-wrap: wrap;
    }
    .bracket-podium-photo {
        width: 56px;
        height: 56px;
        min-width: 56px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,.12);
        background: #f8f9fa;
    }
    .bracket-podium-card--first .bracket-podium-photo {
        width: 64px;
        height: 64px;
        min-width: 64px;
        border-color: #ffc107;
    }
    .bracket-podium-photo--empty {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
        font-size: 1.35rem;
    }
    .bracket-podium-names {
        font-weight: 700;
        font-size: 0.92rem;
        line-height: 1.35;
        word-break: break-word;
    }
    .bracket-podium-card--first .bracket-podium-names { font-size: 1.05rem; }
    @media (max-width: 768px) {
        .bracket-cols { gap: 1.75rem; }
        .bracket-board {
            min-width: max(100%, calc(var(--round-count) * 300px + (var(--round-count) - 1) * 1.75rem));
        }
        .bracket-podium-card--first,
        .bracket-podium-card--second,
        .bracket-podium-card--third { order: 0; }
        .bracket-podium-card { max-width: none; }
    }
</style>
@endpush
@endonce
