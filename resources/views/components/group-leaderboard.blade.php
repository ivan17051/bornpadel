@props(['standings', 'turnamen' => null, 'refreshable' => false])

<div class="group-leaderboard"
     @if($refreshable)
         id="live-leaderboard"
         data-refresh-url="{{ route('api.guest.standings', array_filter(['id_turnamen' => optional($turnamen)->id])) }}"
     @endif>
    @if ($turnamen)
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-bar-chart-steps me-2"></i>Klasemen Grup
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
            Belum ada data klasemen grup.
        </div>
    @else
        <div class="row g-4">
            @foreach ($standings as $grup)
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold py-3">
                            <i class="bi bi-diagram-3 me-2 text-primary"></i>{{ $grup['nama'] }}
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3rem">#</th>
                                            <th>Pemain</th>
                                            <th class="text-center">Poin</th>
                                            <th class="text-center d-none d-sm-table-cell">Set</th>
                                            <th class="text-center d-none d-md-table-cell">Games</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($grup['standings'] as $row)
                                            <tr class="{{ $row['rank'] === 1 ? 'table-success' : '' }}">
                                                <td class="text-center fw-bold">
                                                    @if ($row['rank'] === 1)
                                                        <i class="bi bi-trophy-fill text-warning"></i>
                                                    @else
                                                        {{ $row['rank'] }}
                                                    @endif
                                                </td>
                                                <td class="fw-semibold">{{ $row['nama'] }}</td>
                                                <td class="text-center">
                                                    <span class="badge text-bg-primary">{{ $row['poin_didapat'] }}</span>
                                                </td>
                                                <td class="text-center d-none d-sm-table-cell">{{ $row['set_menang'] }}</td>
                                                <td class="text-center d-none d-md-table-cell">{{ $row['games_menang'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($refreshable)
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>
        @endif
    @endif
</div>
