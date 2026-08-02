@props([
    'standings',
    'turnamen' => null,
    'refreshable' => false,
])

<div class="mahjong-leaderboard"
     @if($refreshable)
         id="live-leaderboard"
         data-refresh-url="{{ route('api.guest.standings', array_filter(['id_turnamen' => optional($turnamen)->id])) }}"
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
        @foreach ($standings as $section)
            <div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-layers me-1 text-primary"></i>Babak {{ $section['babak'] }}
                    </h6>
                    @if (! empty($section['is_active']))
                        <span class="badge text-bg-success">Berlangsung</span>
                    @endif
                </div>

                @include('components.partials.mahjong-babak-table', [
                    'babak' => $section['babak'],
                    'rounds' => collect($section['rounds'] ?? []),
                    'rows' => collect($section['rows'] ?? []),
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
