@props(['standings', 'turnamen' => null, 'refreshable' => false, 'overall' => collect()])

<div class="mahjong-leaderboard"
     @if($refreshable)
         id="live-leaderboard"
         data-refresh-url="{{ route('api.guest.standings', array_filter(['id_turnamen' => optional($turnamen)->id])) }}"
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

    @if ($standings->isEmpty() && $overall->isEmpty())
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

                @if (($section['groups'] ?? collect())->isEmpty())
                    <div class="alert alert-light border mb-0">Belum ada data pemain pada babak ini.</div>
                @else
                    <div class="row g-4">
                        @foreach ($section['groups'] as $grup)
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
                                                        <th class="text-center">Poin Babak</th>
                                                        <th class="text-center">Total</th>
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
                                                            <td class="fw-semibold">
                                                                <x-pemain-names :pemain-ids="$row['pemain_ids'] ?? []" :nama="$row['nama']" />
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge text-bg-secondary">{{ $row['poin_babak'] ?? $row['poin_didapat'] ?? 0 }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge text-bg-primary">{{ $row['total_poin'] ?? 0 }}</span>
                                                            </td>
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
                @endif
            </div>
        @endforeach

        @if ($overall->isNotEmpty())
            <!-- <div class="mt-2">
                <h6 class="fw-semibold mb-3">
                    <i class="bi bi-trophy me-1 text-warning"></i>Klasemen Akumulasi
                </h6>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:3rem">#</th>
                                        <th>Pemain</th>
                                        <th class="text-center d-none d-md-table-cell">Grup</th>
                                        <th class="text-center">Total Poin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($overall as $row)
                                        <tr class="{{ $row['rank'] === 1 ? 'table-success' : '' }}">
                                            <td class="text-center fw-bold">
                                                @if ($row['rank'] === 1)
                                                    <i class="bi bi-trophy-fill text-warning"></i>
                                                @else
                                                    {{ $row['rank'] }}
                                                @endif
                                            </td>
                                            <td class="fw-semibold">
                                                <x-pemain-names :pemain-ids="$row['pemain_ids'] ?? []" :nama="$row['nama']" />
                                            </td>
                                            <td class="text-center text-muted d-none d-md-table-cell">
                                                {{ $row['grup_nama'] ?? '—' }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge text-bg-primary">{{ $row['total_poin'] ?? 0 }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div> -->
        @endif

        @if ($refreshable)
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>
        @endif
    @endif
</div>
