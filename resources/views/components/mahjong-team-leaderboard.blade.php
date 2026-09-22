@props([
    'standings',
    'turnamen' => null,
    'kategori' => null,
    'refreshable' => false,
])

<div class="mahjong-team-leaderboard"
     @if($refreshable)
         id="live-leaderboard"
         data-refresh-url="{{ route('api.guest.standings', array_filter([
             'id_turnamen' => optional($turnamen)->id,
             'id_kategori' => optional($kategori)->id,
         ])) }}"
         data-profile-base="{{ url('/pemain') }}/"
         data-mahjong-team="1"
     @endif>
    @if ($turnamen)
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-bar-chart-steps me-2"></i>Klasemen Tim
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
            Belum ada data klasemen tim.
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:3rem">#</th>
                                <th>Tim</th>
                                <th class="text-center" style="width:7rem">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($standings as $row)
                                @php
                                    $members = collect($row['members'] ?? $row['standings'] ?? []);
                                @endphp
                                <tr class="{{ (int) ($row['rank'] ?? 0) === 1 ? 'table-success' : '' }}">
                                    <td class="text-center fw-bold">
                                        @if ((int) ($row['rank'] ?? 0) === 1)
                                            <i class="bi bi-trophy-fill text-warning"></i>
                                        @else
                                            {{ $row['rank'] }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $row['nama'] }}</div>
                                        @if ($members->isNotEmpty())
                                            <ul class="list-unstyled mb-0 mt-1 small text-muted">
                                                @foreach ($members as $member)
                                                    <li>
                                                        <x-pemain-link
                                                            :id="$member['id_pemain'] ?? null"
                                                            :name="$member['nama'] ?? '—'"
                                                        />
                                                        <span class="ms-1">{{ (int) ($member['poin_didapat'] ?? 0) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge text-bg-primary">{{ (int) ($row['total_poin'] ?? 0) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2 border-top bg-light small text-muted">
                    Peringkat berdasarkan total poin tim. Poin tiap pemain tercantum di bawah nama tim.
                </div>
            </div>
        </div>
    @endif

    @if ($refreshable)
        <p class="text-muted small text-end mt-2 mb-0">
            <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
        </p>
    @endif
</div>
