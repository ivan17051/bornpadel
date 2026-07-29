@props(['standings', 'turnamen' => null, 'refreshable' => false, 'showGroupHistory' => true])

@php
    $isDouble = optional($turnamen)->playsAsPairs();
    $showHistory = $showGroupHistory && $turnamen && ! $turnamen->isMahjong();
    $historyUrl = $showHistory ? route('api.guest.standings.group-history') : null;
@endphp

<div class="group-leaderboard"
     @if($refreshable)
         id="live-leaderboard"
         data-refresh-url="{{ route('api.guest.standings', array_filter(['id_turnamen' => optional($turnamen)->id])) }}"
         @if($showHistory) data-show-group-history="1" @endif
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
                        <div class="card-header bg-white fw-semibold py-3 d-flex align-items-center justify-content-between gap-2">
                            <span>
                                <i class="bi bi-diagram-3 me-2 text-primary"></i>{{ $grup['nama'] }}
                                @if (! empty($grup['matches_complete']))
                                &nbsp;<span class="text-success"
                                      title="Semua pertandingan grup sudah selesai">
                                    <i class="bi bi-check-circle "></i>
                                </span>
                                @endif
                            </span>
                            
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3rem">#</th>
                                            <th>{{ optional($turnamen)->playsAsPairs() ? 'Pasangan' : 'Pemain' }}</th>
                                            @if (!empty($grup['is_mahjong']))
                                                <th class="text-center">Akumulasi</th>
                                                <th class="text-center">Babak</th>
                                                <th class="text-center">Total</th>
                                            @else
                                                <th class="text-center">Poin</th>
                                                <th class="text-center d-none d-sm-table-cell">Set</th>
                                                <th class="text-center d-none d-md-table-cell" title="Selisih game (game dimenangkan − game dikalahkan)">GD</th>
                                                @if ($showHistory)
                                                    <th class="text-end" style="width:4rem"></th>
                                                @endif
                                            @endif
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
                                                @if (!empty($grup['is_mahjong']))
                                                    <td class="text-center text-muted">{{ $row['poin_akumulasi'] ?? 0 }}</td>
                                                    <td class="text-center">
                                                        <span class="badge text-bg-secondary">{{ $row['poin_didapat'] }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge text-bg-primary">{{ $row['total_poin'] ?? $row['poin_didapat'] }}</span>
                                                    </td>
                                                @else
                                                    <td class="text-center">
                                                        <span class="badge text-bg-primary">{{ $row['poin_didapat'] }}</span>
                                                    </td>
                                                    <td class="text-center d-none d-sm-table-cell">{{ $row['set_menang'] }}</td>
                                                    <td class="text-center d-none d-md-table-cell">{{ $row['games_diff_label'] ?? \App\Models\GrupMember::formatGameDifference($row['games_menang'] ?? 0) }}</td>
                                                    @if ($showHistory)
                                                        <td class="text-end">
                                                            @if (! empty($row['id_peserta']))
                                                                <button type="button"
                                                                        class="btn btn-sm btn-outline-secondary btn-group-stage-history"
                                                                        title="Riwayat pertandingan"
                                                                        aria-label="Riwayat pertandingan {{ $row['nama'] }}"
                                                                        data-grup-id="{{ $grup['id'] }}"
                                                                        data-peserta-id="{{ $row['id_peserta'] }}"
                                                                        data-participant-name="{{ $row['nama'] }}">
                                                                    <i class="bi bi-clock-history"></i>
                                                                </button>
                                                            @endif
                                                        </td>
                                                    @endif
                                                @endif
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

@if ($showHistory)
    <x-group-stage-history-modal :history-url="$historyUrl" />
@endif
