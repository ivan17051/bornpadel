@props([
    'standings',
    'turnamen' => null,
    'kategori' => null,
    'refreshable' => false,
])

<div class="mahjong-leaderboard"
     @if($refreshable)
         id="live-leaderboard"
         data-refresh-url="{{ route('api.guest.standings', array_filter([
             'id_turnamen' => optional($turnamen)->id,
             'id_kategori' => optional($kategori)->id,
         ])) }}"
         data-profile-base="{{ url('/pemain') }}/"
         data-mahjong="1"
     @endif>
    @if ($turnamen)
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-bar-chart-steps me-2"></i>Klasemen Mahjong
                <small class="text-muted fw-normal">— {{ $turnamen->nama }}</small>
            </h5>
            @if ($refreshable)
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-leaderboard">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            @endif
        </div>
    @endif

    @if ($standings->isEmpty())
        <div class="alert alert-light border text-center mb-0">
            <i class="bi bi-trophy text-muted d-block mb-2 fs-4"></i>
            Belum ada data klasemen.
        </div>
    @else
        <div class="alert alert-light border small mb-3">
            <strong>Cara peringkat:</strong> Total babak → Menang (W) → Akumulasi.
            Baris hijau menandai pemain yang lolos ke babak berikutnya.
        </div>
        @foreach ($standings as $section)
            @php
                $advanceKind = $section['advance_kind'] ?? 'none';
            @endphp
            <div class="mb-4">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-layers me-1 text-primary"></i>Babak {{ $section['babak'] }}
                    </h6>
                    @if (! empty($section['is_active']))
                        <span class="badge text-bg-success">Berlangsung</span>
                    @endif
                    @if (! empty($section['is_final']))
                        <span class="badge text-bg-warning text-dark">Final</span>
                    @elseif ($advanceKind === 'confirmed' && ! empty($section['next_babak']))
                        <span class="badge text-bg-primary">Lolos ke Babak {{ $section['next_babak'] }}</span>
                    @elseif ($advanceKind === 'preview' && ! empty($section['advance_count']))
                        <span class="badge border text-secondary">Pratinjau {{ $section['advance_count'] }} lolos</span>
                    @endif
                </div>

                @include('components.partials.mahjong-babak-table', [
                    'babak' => $section['babak'],
                    'rounds' => collect($section['rounds'] ?? []),
                    'rows' => collect($section['rows'] ?? []),
                    'advanceKind' => $advanceKind,
                    'advanceNote' => $section['advance_note'] ?? null,
                    'rankingNote' => $section['ranking_note'] ?? null,
                ])
            </div>
        @endforeach

        @if ($refreshable)
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>
        @endif
    @endif
</div>
