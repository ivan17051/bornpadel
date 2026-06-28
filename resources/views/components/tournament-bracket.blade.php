@props(['bracket', 'turnamen' => null, 'refreshable' => false])

<div class="tournament-bracket-wrapper"
     @if($refreshable)
         id="live-bracket"
         data-refresh-url="{{ route('api.guest.bracket', array_filter(['id_turnamen' => optional($turnamen)->id])) }}"
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
        <div class="bracket-tree overflow-auto pb-3">
            <div class="bracket-rounds d-flex align-items-stretch gap-0">
                @foreach ($bracket as $roundIndex => $round)
                    <div class="bracket-round flex-shrink-0">
                        <div class="bracket-round-title text-center text-uppercase small fw-bold text-muted mb-3">
                            {{ $round['nama_ronde'] }}
                        </div>
                        <div class="bracket-matches d-flex flex-column justify-content-around h-100">
                            @foreach ($round['matches'] as $match)
                                <div class="bracket-match {{ $match['status'] === 'completed' ? 'is-completed' : '' }} {{ $match['pemenang_id'] ? 'has-winner' : '' }}">
                                    <div class="bracket-player {{ $match['pemenang_id'] && $match['pemain1_id'] === $match['pemenang_id'] ? 'is-winner' : '' }} {{ ! $match['pemain1_id'] && empty($match['pemain1_ids']) ? 'is-tbd' : '' }}">
                                        <span class="bracket-player-name">
                                            @if (! empty($match['pemain1_ids']))
                                                <x-pemain-names :pemain-ids="$match['pemain1_ids']" :nama="$match['pemain1']" />
                                            @else
                                                {{ $match['pemain1'] }}
                                            @endif
                                        </span>
                                        @if ($match['skor'] && $match['status'] === 'completed')
                                            <span class="bracket-score-badge">{{ collect(explode(', ', $match['skor']))->map(fn($s) => explode('-', $s)[0] ?? '')->implode(' ') }}</span>
                                        @endif
                                    </div>
                                    <div class="bracket-player {{ $match['pemenang_id'] && $match['pemain2_id'] === $match['pemenang_id'] ? 'is-winner' : '' }} {{ ! $match['pemain2_id'] && empty($match['pemain2_ids']) ? 'is-tbd' : '' }}">
                                        <span class="bracket-player-name">
                                            @if (! empty($match['pemain2_ids']))
                                                <x-pemain-names :pemain-ids="$match['pemain2_ids']" :nama="$match['pemain2']" />
                                            @else
                                                {{ $match['pemain2'] }}
                                            @endif
                                        </span>
                                        @if ($match['skor'] && $match['status'] === 'completed')
                                            <span class="bracket-score-badge">{{ collect(explode(', ', $match['skor']))->map(fn($s) => explode('-', $s)[1] ?? '')->implode(' ') }}</span>
                                        @endif
                                    </div>
                                    @if ($match['status'] === 'scheduled' && $match['pemain1_id'] && $match['pemain2_id'])
                                        <div class="bracket-match-status"><span class="badge bg-secondary">Upcoming</span></div>
                                    @elseif ($match['status'] === 'scheduled')
                                        <div class="bracket-match-status"><span class="badge bg-light text-dark border">Menunggu</span></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if (! $loop->last)
                        <div class="bracket-connector flex-shrink-0"></div>
                    @endif
                @endforeach
            </div>
        </div>

        @php
            $champion = collect($bracket)->last();
            $finalWinner = $champion ? collect($champion['matches'])->first()['pemenang'] ?? null : null;
        @endphp
        @if ($finalWinner)
            <div class="champion-banner text-center mt-4 p-4 rounded-3">
                <i class="bi bi-trophy-fill text-warning fs-2 d-block mb-2"></i>
                <div class="small text-muted text-uppercase">Juara Turnamen</div>
                <div class="h4 fw-bold mb-0">{{ $finalWinner }}</div>
            </div>
        @endif

        @if ($refreshable)
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>
        @endif
    @endif
</div>

@once
@push('styles')
<style>
    .bracket-tree { min-height: 320px; }
    .bracket-rounds { min-width: max-content; padding: 1rem 0; }
    .bracket-round { width: 220px; min-height: 100%; }
    .bracket-round-title { letter-spacing: 0.06em; }
    .bracket-matches { gap: 2.5rem; min-height: 280px; }
    .bracket-match {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,.04);
        position: relative;
        min-width: 200px;
    }
    .bracket-match.is-completed { border-color: #cda858; }
    .bracket-match.has-winner { box-shadow: 0 4px 12px rgba(205, 168, 88, .18); }
    .bracket-player {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.55rem 0.75rem;
        font-size: 0.875rem;
        border-bottom: 1px solid #f0f0f0;
        gap: 0.5rem;
    }
    .bracket-player:last-of-type { border-bottom: none; }
    .bracket-player.is-winner { background: #f5ecd4; font-weight: 600; }
    .bracket-player.is-tbd { color: #adb5bd; font-style: italic; }
    .bracket-player-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bracket-score-badge { font-size: 0.75rem; color: #6c757d; font-weight: 600; flex-shrink: 0; }
    .bracket-match-status { position: absolute; top: -0.65rem; right: 0.5rem; }
    .bracket-connector {
        width: 40px;
        align-self: stretch;
        background: linear-gradient(90deg, transparent 45%, #ced4da 45%, #ced4da 55%, transparent 55%);
        background-size: 100% 50%;
        background-repeat: no-repeat;
        background-position: center;
        margin: 0 0.25rem;
    }
    .champion-banner {
        background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
        border: 1px solid #ffc107;
    }
    @media (max-width: 768px) {
        .bracket-round { width: 180px; }
        .bracket-match { min-width: 165px; }
        .bracket-matches { gap: 1.5rem; }
    }
</style>
@endpush
@endonce
